<?php

namespace App\Http\Controllers\Vendedor;

use App\Actions\Billing\AnularVentaPorEnviar;
use App\Actions\Billing\CorregirComprobante;
use App\Actions\Billing\EmitElectronicDocument;
use App\Actions\Certificates\EmitirCertificadoDeServicio;
use App\Actions\Sales\CambiarUnidadVendida;
use App\Actions\Sales\ConfirmSale;
use App\Actions\Sales\CreateSale;
use App\Actions\Sales\DescartarVentaSinComprobante;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\CorregirComprobanteRequest;
use App\Http\Requests\Sales\StoreSaleRequest;
use App\Models\CashRegister;
use App\Models\Client;
use App\Models\CompanySetting;
use App\Models\Quote;
use App\Models\QuoteItem;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Sede;
use App\Models\Service;
use App\Models\ServiceOrder;
use App\Models\Team;
use App\Services\ServiceOrders\ServiceOrderNumberGenerator;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class SaleController extends Controller
{
    public function index(Team $current_team, Request $request): Response
    {
        $estado = $request->string('estado')->toString();
        $comprobante = $request->string('comprobante')->toString();
        $vendedorId = $request->user()->id;
        $sedeId = $request->user()->sedeRestringidaId();

        $sales = Sale::query()
            ->with(['client', 'electronicDocuments'])
            ->when($sedeId, fn ($query) => $query->where('sede_id', $sedeId))
            ->when($estado !== '' && $estado !== 'todas', fn ($query) => $query->where('estado', $estado))
            ->when($comprobante === 'nota_venta', fn ($query) => $query->where('comprobante_tipo', Sale::NOTA_VENTA))
            ->when($comprobante === 'sunat', fn ($query) => $query->where('comprobante_tipo', '!=', Sale::NOTA_VENTA))
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Sale $sale) => [
                'id' => $sale->id,
                'numero_interno' => $sale->numero_interno,
                'numero_nota_venta' => $sale->numero_nota_venta,
                'cliente' => $sale->client->razon_social,
                'fecha' => $sale->fecha->toDateString(),
                'comprobante_tipo' => $sale->comprobante_tipo,
                'comprobante' => $sale->numeroComprobante(),
                'sunat_estado' => $sale->electronicDocuments
                    ->whereIn('tipo', ['factura', 'boleta'])
                    ->sortBy('id')
                    ->last()?->sunat_estado,
                'total' => $sale->total,
                'estado' => $sale->estado,
            ]);

        return Inertia::render('vendedor/ventas/index', [
            'sales' => $sales,
            'filters' => ['estado' => $estado, 'comprobante' => $comprobante],
            // KPIs siempre acotados al vendedor autenticado: NUNCA acumulado
            // de toda la empresa (regla de la sección 77.3 del doc maestro).
            'kpis' => [
                'ventas_del_mes' => Sale::where('vendedor_id', $vendedorId)
                    ->whereBetween('fecha', [now()->startOfMonth(), now()->endOfMonth()])
                    ->sum('total'),
                'comprobantes' => Sale::where('vendedor_id', $vendedorId)->count(),
                'pendientes_confirmar' => Sale::where('vendedor_id', $vendedorId)->where('estado', 'borrador')->count(),
            ],
        ]);
    }

    public function create(Team $current_team, Request $request): Response
    {
        $sedeId = $request->user()->sedeRestringidaId();

        return Inertia::render('vendedor/ventas/nueva', [
            'caja_abierta' => CashRegister::query()
                ->where('vendedor_id', $request->user()->id)
                ->where('estado', 'abierto')
                ->exists(),
            'clientesVarios' => Client::clientesVarios()->only(['id', 'tipo_documento', 'razon_social', 'numero_documento']),
            'limiteBoletaSinIdentificar' => Sale::LIMITE_BOLETA_SIN_IDENTIFICAR,
            'sedes' => Sede::query()
                ->where('activo', true)
                ->when($sedeId, fn ($query) => $query->where('id', $sedeId))
                ->orderBy('nombre')
                ->get(['id', 'nombre']),
            'quote' => $request->filled('cotizacion')
                ? $this->cotizacionParaVenta($request->integer('cotizacion'), $sedeId)
                : null,
            'venta' => $request->filled('orden_servicio')
                ? $this->ordenParaCobro($request->integer('orden_servicio'), $sedeId)
                : ($request->filled('rehacer')
                ? $this->ventaParaFormulario($request->integer('rehacer'), $sedeId, comoCopia: true)
                : null),
        ]);
    }

    /**
     * Editar una venta en borrador: el mismo formulario de nueva venta, ya
     * lleno. Al guardar conserva su número.
     */
    public function edit(Team $current_team, Sale $sale, Request $request): Response|RedirectResponse
    {
        $this->assertSedeAccess($request, $sale);

        if ($sale->estado !== 'borrador') {
            Inertia::flash('toast', ['type' => 'error', 'message' => "La venta {$sale->numero_interno} ya se emitió: corrígela desde su detalle."]);

            return redirect()->route('vendedor.ventas.show', ['current_team' => $current_team, 'sale' => $sale]);
        }

        $sedeId = $request->user()->sedeRestringidaId();

        return Inertia::render('vendedor/ventas/nueva', [
            'clientesVarios' => Client::clientesVarios()->only(['id', 'tipo_documento', 'razon_social', 'numero_documento']),
            'limiteBoletaSinIdentificar' => Sale::LIMITE_BOLETA_SIN_IDENTIFICAR,
            'sedes' => Sede::query()
                ->where('activo', true)
                ->when($sedeId, fn ($query) => $query->where('id', $sedeId))
                ->orderBy('nombre')
                ->get(['id', 'nombre']),
            'quote' => null,
            'venta' => $this->ventaParaFormulario($sale->id, $sedeId, comoCopia: false),
        ]);
    }

    public function update(Team $current_team, Sale $sale, StoreSaleRequest $request, CreateSale $createSale, ConfirmSale $confirmSale): RedirectResponse
    {
        $this->assertSedeAccess($request, $sale);

        $data = $request->safe()->except(['items', 'emitir']);
        $items = $request->safe()->input('items');

        if ($sedeId = $request->user()->sedeRestringidaId()) {
            $data['sede_id'] = $sedeId;
        }

        $emitir = $request->boolean('emitir');

        $sale = DB::transaction(function () use ($sale, $data, $items, $request, $createSale, $confirmSale, $emitir) {
            $sale = $createSale->actualizar($sale, $data, $items, $request->user()->id);

            return $emitir ? $confirmSale->handle($sale) : $sale;
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => $emitir
            ? "Venta {$sale->numero_interno} corregida y emitida."
            : "Borrador {$sale->numero_interno} actualizado."]);

        return redirect()->route('vendedor.ventas.show', ['current_team' => $current_team, 'sale' => $sale]);
    }

    /**
     * Precio o producto mal en un comprobante que aún no se envió a SUNAT:
     * se anula (el número se libera) y se abre una copia lista para corregir.
     */
    public function corregirProductos(Team $current_team, Sale $sale, Request $request, AnularVentaPorEnviar $anularVentaPorEnviar): RedirectResponse
    {
        $this->assertSedeAccess($request, $sale);

        $anularVentaPorEnviar->handle($sale);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Venta {$sale->numero_interno} anulada. Corrige los datos y vuelve a emitir."]);

        return redirect()->route('vendedor.ventas.create', ['current_team' => $current_team, 'rehacer' => $sale->id]);
    }

    /**
     * Datos de una venta para llenar el formulario: para editar su borrador
     * o, como copia, para rehacerla después de anularla.
     *
     * @return array<string, mixed>|null
     */
    protected function ventaParaFormulario(int $saleId, ?int $sedeId, bool $comoCopia): ?array
    {
        $sale = Sale::query()
            ->with('client', 'items.product', 'items.service', 'items.inventoryUnit', 'installments')
            ->when($sedeId, fn ($query) => $query->where('sede_id', $sedeId))
            ->find($saleId);

        if (! $sale) {
            return null;
        }

        return [
            'id' => $comoCopia ? null : $sale->id,
            'numero_interno' => $sale->numero_interno,
            'client' => $sale->client->only(['id', 'tipo_documento', 'razon_social', 'numero_documento']),
            'sede_id' => $sale->sede_id,
            'destino' => $sale->destino,
            'referencia' => $sale->referencia,
            'condicion_pago' => $sale->esCredito() ? 'credito' : 'contado',
            'medio_pago' => $sale->medio_pago ?? 'efectivo',
            'numero_operacion' => $sale->numero_operacion,
            'comprobante_tipo' => $sale->comprobante_tipo,
            'observaciones' => $sale->observaciones,
            'cuotas' => $comoCopia ? [] : $sale->installments->map(fn ($cuota) => [
                'fecha_vencimiento' => $cuota->fecha_vencimiento->toDateString(),
                'monto' => (float) $cuota->monto,
            ])->values()->all(),
            'items' => $sale->items
                ->reject(fn (SaleItem $item) => $item->tipo_linea === 'recarga_servicio' && ! $item->service_id)
                ->map(fn (SaleItem $item) => match ($item->tipo_linea) {
                    'unidad_nueva' => [
                        'tipo_linea' => 'unidad_nueva',
                        'numero_serie' => $item->inventoryUnit?->numero_serie,
                        'product_id' => $item->product_id,
                        'inventory_unit_id' => $item->inventory_unit_id,
                        'nombre' => $item->product?->nombre,
                        'detalle' => collect([$item->inventoryUnit?->capacidad, $item->inventoryUnit?->marca])->filter()->implode(' · '),
                        'cantidad' => 1,
                        'precio_unitario' => (float) $item->precio_unitario,
                        'descuento' => (float) $item->descuento,
                    ],
                    'producto' => [
                        'tipo_linea' => 'producto',
                        'product_id' => $item->product_id,
                        'nombre' => $item->product?->nombre,
                        'cantidad' => (int) $item->cantidad,
                        'precio_unitario' => (float) $item->precio_unitario,
                        'descuento' => (float) $item->descuento,
                    ],
                    default => [
                        'tipo_linea' => 'servicio',
                        'service_id' => $item->service_id,
                        'nombre' => $item->service?->nombre,
                        'cantidad' => (int) $item->cantidad,
                        'precio_unitario' => (float) $item->precio_unitario,
                        'descuento' => (float) $item->descuento,
                    ],
                })
                ->values()
                ->all(),
        ];
    }

    /**
     * Cotización aceptada que se pasa a venta: precarga el cliente y
     * muestra los ítems cotizados para escanear sus series.
     *
     * @return array{id: int, numero: string, client: array{id: int, tipo_documento: string, razon_social: string, numero_documento: string}, items: list<array{tipo: string, product_id: int|null, service_id: int|null, nombre: string, codigo: string|null, serializado: bool, cantidad: int, precio_unitario: float}>, referencia: string|null}|null
     */
    protected function cotizacionParaVenta(int $quoteId, ?int $sedeId): ?array
    {
        $quote = Quote::query()
            ->with('client', 'items.product', 'items.service')
            ->whereIn('estado', ['borrador', 'emitida', 'enviada', 'pendiente', 'aceptada'])
            ->when($sedeId, fn ($query) => $query->where('sede_id', $sedeId))
            ->find($quoteId);

        if (! $quote) {
            return null;
        }

        return [
            'id' => $quote->id,
            'numero' => $quote->numero,
            'client' => $quote->client->only(['id', 'tipo_documento', 'razon_social', 'numero_documento']),
            'items' => $quote->items->map(fn (QuoteItem $item) => [
                'tipo' => $item->esServicio() ? 'service' : 'product',
                'product_id' => $item->product_id,
                'service_id' => $item->service_id,
                'nombre' => $item->esServicio() ? $item->service?->nombre : $item->product?->nombre,
                'codigo' => $item->esServicio() ? $item->service?->codigo : $item->product?->codigo,
                'serializado' => (bool) $item->product?->serializado,
                'cantidad' => (int) $item->cantidad,
                'precio_unitario' => (float) $item->precio_unitario,
            ])->values()->all(),
            'referencia' => $quote->referencia,
        ];
    }

    public function store(Team $current_team, StoreSaleRequest $request, CreateSale $createSale, ServiceOrderNumberGenerator $numberGenerator): RedirectResponse
    {
        $data = $request->safe()->except(['items', 'emitir']);
        $items = $request->safe()->input('items');

        if ($sedeId = $request->user()->sedeRestringidaId()) {
            $data['sede_id'] = $sedeId;
        }

        // Emitir en el mismo paso: si algo falla (RUC, stock), no queda nada
        // a medias y el error sale en el formulario.
        $emitir = $request->boolean('emitir');

        $serviceOrderId = $request->integer('service_order_id') ?: null;
        $sale = DB::transaction(function () use ($data, $items, $request, $createSale, $emitir, $serviceOrderId, $numberGenerator) {
            $serviceOrder = $serviceOrderId ? ServiceOrder::lockForUpdate()->findOrFail($serviceOrderId) : null;
            if ($serviceOrder?->sale_id) {
                throw ValidationException::withMessages(['service_order_id' => 'Esta orden ya fue cobrada.']);
            }
            if ($serviceOrder && $serviceOrder->client_id !== (int) $data['client_id']) {
                throw ValidationException::withMessages(['client_id' => 'La venta debe pertenecer al cliente de la orden.']);
            }
            $sale = $createSale->handle($data, $items, $request->user()->id);
            $serviceOrder?->update(['sale_id' => $sale->id]);

            if (! $serviceOrder && ! empty($data['quote_id'])) {
                $quote = Quote::query()->with(['items.service', 'equipments'])->findOrFail((int) $data['quote_id']);
                $serviceItem = $quote->items->first(fn (QuoteItem $item): bool => $item->service_id !== null);
                if ($serviceItem?->service) {
                    $serviceOrder = ServiceOrder::create(['codigo' => $numberGenerator->next(), 'client_id' => $quote->client_id, 'sede_id' => $sale->sede_id, 'vehicle_id' => $quote->vehicle_id, 'quote_id' => $quote->id, 'sale_id' => $sale->id, 'service_id' => $serviceItem->service_id, 'fecha' => today(), 'departamento_tecnico' => 'planta', 'prioridad' => 'normal', 'observaciones' => "Creada desde {$quote->numero}", 'estado' => 'pendiente_recepcion']);
                    $serviceOrder->equipments()->sync($quote->equipments->modelKeys());
                }
            }

            return $emitir ? app(ConfirmSale::class)->handle($sale) : $sale;
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => $emitir
            ? "Venta {$sale->numero_interno} emitida."
            : "Borrador {$sale->numero_interno} guardado. Emítelo cuando el cliente confirme."]);

        return redirect()->route('vendedor.ventas.show', [
            'current_team' => $current_team,
            'sale' => $sale,
        ]);
    }

    /** @return array<string, mixed>|null */
    protected function ordenParaCobro(int $orderId, ?int $sedeId): ?array
    {
        $order = ServiceOrder::with(['client', 'equipments', 'service', 'deficiencies.authorization.cotizacionAdicional.items.product', 'deficiencies.authorization.cotizacionAdicional.items.service'])->when($sedeId, fn ($query) => $query->where('sede_id', $sedeId))->find($orderId);
        if (! $order || $order->sale_id) {
            return null;
        }

        $services = Service::where('activo', true)->get();
        $orderService = $order->service ?? $services->first();
        $equipments = $order->equipments->isEmpty() ? collect([null]) : $order->equipments;
        $items = $equipments->map(function ($equipment) use ($services, $orderService): array {
            $service = $orderService ?? $services->first(fn (Service $service): bool => str_contains(mb_strtolower($service->nombre.' '.$service->descripcion), mb_strtolower((string) $equipment?->capacidad)), $services->first());

            return ['tipo_linea' => $equipment ? 'recarga_servicio' : 'servicio', 'numero_serie' => $equipment?->numero_serie, 'service_id' => $service->id, 'nombre' => $service->nombre, 'cantidad' => 1, 'precio_unitario' => (float) $service->precio_venta, 'descuento' => 0];
        });
        $additionalItems = $order->deficiencies->where('estado', 'autorizada')->flatMap(function ($deficiency) {
            $cotizacion = $deficiency->authorization?->cotizacionAdicional;

            return $cotizacion === null ? [] : $cotizacion->items;
        })->map(fn (QuoteItem $item): array => ['tipo_linea' => $item->service_id ? 'servicio' : 'producto', 'service_id' => $item->service_id, 'product_id' => $item->product_id, 'nombre' => $item->service_id ? $item->service->nombre : $item->product->nombre, 'cantidad' => (int) $item->cantidad, 'precio_unitario' => (float) $item->precio_unitario, 'descuento' => (float) $item->descuento]);
        $items = $items->concat($additionalItems)->values()->all();

        return ['id' => null, 'numero_interno' => $order->codigo, 'service_order_id' => $order->id, 'client' => $order->client->only(['id', 'tipo_documento', 'razon_social', 'numero_documento']), 'sede_id' => $order->sede_id, 'destino' => 'local_cliente', 'referencia' => $order->codigo, 'condicion_pago' => 'contado', 'medio_pago' => 'efectivo', 'numero_operacion' => null, 'comprobante_tipo' => $order->client->tipo_documento === 'ruc' ? 'factura' : 'boleta', 'observaciones' => "Cobro de {$order->codigo}", 'cuotas' => [], 'items' => $items];
    }

    public function show(Team $current_team, Sale $sale, Request $request): Response
    {
        $this->assertSedeAccess($request, $sale);

        $sale->load('items.product', 'items.service', 'items.inventoryUnit', 'items.equipment', 'client', 'payments', 'electronicDocuments');

        $sale->items->each(fn ($item) => $item->setAttribute(
            'numero_serie',
            $item->inventoryUnit?->numero_serie ?? $item->equipment?->numero_serie,
        ));

        return Inertia::render('vendedor/ventas/show', [
            'sale' => [
                ...$sale->toArray(),
                'fecha' => $sale->fecha->toDateString(),
                'client' => $sale->client->only(['id', 'tipo_documento', 'razon_social', 'numero_documento']),
            ],
            'clientesVarios' => Client::clientesVarios()->only(['id', 'tipo_documento', 'razon_social', 'numero_documento']),
            'limiteBoletaSinIdentificar' => Sale::LIMITE_BOLETA_SIN_IDENTIFICAR,
            'certificados' => SaleCertificateController::certificados($sale),
            'tieneEquipos' => $sale->items->contains(fn ($item) => $item->equipment_id !== null),
            'tiposServicio' => EmitirCertificadoDeServicio::tiposDeServicio()
                ->map(fn ($tipo) => ['codigo' => $tipo->codigo, 'nombre' => $tipo->nombre])
                ->values(),
        ]);
    }

    public function notaVentaPdf(Team $current_team, Sale $sale, Request $request): HttpResponse
    {
        $this->assertSedeAccess($request, $sale);

        abort_unless($sale->esNotaVenta() && $sale->numero_nota_venta, 404);

        $sale->load('client', 'items.product', 'items.service', 'items.equipment');

        return Pdf::loadView('pdf.nota-venta', [
            'sale' => $sale,
            'company' => CompanySetting::current(),
        ])->setPaper('a4')->stream("{$sale->numero_nota_venta}.pdf");
    }

    public function confirm(Team $current_team, Sale $sale, ConfirmSale $confirmSale, Request $request): RedirectResponse
    {
        $this->assertSedeAccess($request, $sale);

        $confirmSale->handle($sale);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Venta {$sale->numero_interno} confirmada."]);

        return redirect()->route('vendedor.ventas.show', [
            'current_team' => $current_team,
            'sale' => $sale,
        ]);
    }

    /**
     * Cambia factura ↔ boleta o el cliente de un comprobante que aún no se
     * envió a SUNAT o que fue rechazado, sin nota de crédito.
     */
    public function corregirComprobante(Team $current_team, Sale $sale, CorregirComprobanteRequest $request, CorregirComprobante $corregirComprobante): RedirectResponse
    {
        $this->assertSedeAccess($request, $sale);

        $documento = $corregirComprobante->handle(
            $sale,
            $request->validated('comprobante_tipo'),
            (int) $request->validated('client_id'),
            $request->user()->id,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => "Comprobante corregido: {$documento->serie}-{$documento->correlativo}."]);

        return back();
    }

    public function enviarSunat(Team $current_team, Sale $sale, Request $request, EmitElectronicDocument $emitElectronicDocument): RedirectResponse
    {
        $this->assertSedeAccess($request, $sale);

        $documento = $sale->electronicDocuments()->where('sunat_estado', 'por_enviar')->latest('id')->first();

        abort_unless($documento, 422, 'Este comprobante ya se envió a SUNAT.');

        $documento = $emitElectronicDocument->sendDocument($documento);

        Inertia::flash('toast', [
            'type' => in_array($documento->sunat_estado, ['aceptado', 'observado'], true) ? 'success' : 'error',
            'message' => "{$documento->serie}-{$documento->correlativo}: {$documento->sunat_estado}. {$documento->sunat_mensaje}",
        ]);

        return back();
    }

    public function anular(Team $current_team, Sale $sale, Request $request, AnularVentaPorEnviar $anularVentaPorEnviar): RedirectResponse
    {
        $this->assertSedeAccess($request, $sale);

        $anularVentaPorEnviar->handle($sale);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Venta {$sale->numero_interno} anulada; las unidades volvieron al stock."]);

        return back();
    }

    /**
     * Cambia el extintor de una línea por otro del mismo producto, sin nota
     * de crédito (el comprobante no muestra la serie).
     */
    public function cambiarUnidad(Team $current_team, Sale $sale, SaleItem $item, Request $request, CambiarUnidadVendida $cambiar): RedirectResponse
    {
        $this->assertSedeAccess($request, $sale);

        $datos = $request->validate(['numero_serie' => ['required', 'string', 'max:50']]);
        $item = $cambiar->handle($sale, $item, $datos['numero_serie'], $request->user()->id);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Extintor cambiado por {$item->inventoryUnit->numero_serie}. Los certificados se corrigieron."]);

        return back();
    }

    /**
     * Descarta un borrador o anula una nota de venta (nunca fueron a SUNAT).
     */
    public function descartar(Team $current_team, Sale $sale, Request $request, DescartarVentaSinComprobante $descartar): RedirectResponse
    {
        $this->assertSedeAccess($request, $sale);

        $esBorrador = $sale->estado === 'borrador';
        $descartar->handle($sale);

        Inertia::flash('toast', ['type' => 'success', 'message' => $esBorrador
            ? "Borrador {$sale->numero_interno} descartado; las unidades volvieron al stock."
            : "Nota de venta {$sale->numero_nota_venta} anulada; las unidades volvieron al stock."]);

        return back();
    }

    /**
     * Un trabajador con sede asignada solo opera las ventas de su sede.
     */
    protected function assertSedeAccess(Request $request, Sale $sale): void
    {
        $sedeId = $request->user()->sedeRestringidaId();

        abort_if($sedeId !== null && (int) $sale->sede_id !== $sedeId, 404);
    }
}
