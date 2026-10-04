<?php

namespace App\Http\Controllers\Vendedor;

use App\Actions\Billing\AnularVentaPorEnviar;
use App\Actions\Billing\EmitElectronicDocument;
use App\Actions\Certificates\EmitirCertificadoDeServicio;
use App\Actions\Sales\CambiarUnidadVendida;
use App\Actions\Sales\ConfirmSale;
use App\Actions\Sales\CreateSale;
use App\Actions\Sales\DescartarVentaSinComprobante;
use App\Actions\Sales\EditarVentaEmitida;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Vendedor\Concerns\FiltraPorFechas;
use App\Http\Requests\Sales\StoreSaleRequest;
use App\Models\CashRegister;
use App\Models\Client;
use App\Models\ElectronicDocument;
use App\Models\Quote;
use App\Models\QuoteItem;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Sede;
use App\Models\Service;
use App\Models\ServiceOrder;
use App\Models\Team;
use App\Services\Billing\ComprobantePdfService;
use App\Services\ServiceOrders\ServiceOrderNumberGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class SaleController extends Controller
{
    use FiltraPorFechas;

    /**
     * Ventas de un rango de fechas (por defecto, las de hoy), con los totales
     * del vendedor en ese mismo rango.
     */
    public function index(Team $current_team, Request $request): Response
    {
        $estado = $request->string('estado')->toString();
        $comprobante = $request->string('comprobante')->toString();
        $buscar = trim($request->string('buscar')->toString());
        [$desde, $hasta] = $this->rangoDeFechas($request);
        $vendedorId = $request->user()->id;
        $sedeId = $request->user()->sedeRestringidaId();
        $soloDe = $request->user()->vendedorRestringidoId();

        // Cada vendedor ve solo sus ventas; el Gerente, las de todos.
        $sales = Sale::query()
            ->with(['client', 'electronicDocuments'])
            ->when($sedeId, fn ($query) => $query->where('sede_id', $sedeId))
            ->when($soloDe, fn ($query) => $query->where('vendedor_id', $soloDe))
            ->whereBetween('fecha', [$desde->toDateString(), $hasta->toDateString()])
            ->when($estado !== '' && $estado !== 'todas', fn ($query) => $query->where('estado', $estado))
            ->when($comprobante === 'nota_venta', fn ($query) => $query->where('comprobante_tipo', Sale::NOTA_VENTA))
            ->when($comprobante === 'sunat', fn ($query) => $query->where('comprobante_tipo', '!=', Sale::NOTA_VENTA))
            ->when($buscar !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('numero_interno', 'like', "%{$buscar}%")
                ->orWhere('numero_nota_venta', 'like', "%{$buscar}%")
                ->orWhereHas('client', fn ($query) => $query
                    ->where('razon_social', 'like', "%{$buscar}%")
                    ->orWhere('numero_documento', 'like', "%{$buscar}%"))
                ->orWhereHas('electronicDocuments', fn ($query) => $query
                    ->whereRaw("CONCAT(serie, '-', correlativo) like ?", ["%{$buscar}%"]))))
            ->orderByDesc('fecha')
            ->orderByDesc('id')
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
                'editable' => $sale->sePuedeEditar(),
            ]);

        // KPIs siempre acotados al vendedor autenticado: NUNCA acumulado de
        // toda la empresa (regla de la sección 77.3 del doc maestro).
        $emitidas = Sale::query()
            ->where('vendedor_id', $vendedorId)
            ->where('estado', 'confirmada')
            ->whereBetween('fecha', [$desde->toDateString(), $hasta->toDateString()])
            ->get(['total', 'condicion_pago']);

        return Inertia::render('vendedor/ventas/index', [
            'sales' => $sales,
            'filters' => [
                'estado' => $estado,
                'comprobante' => $comprobante,
                'buscar' => $buscar,
                'desde' => $desde->toDateString(),
                'hasta' => $hasta->toDateString(),
            ],
            'hoy' => today()->toDateString(),
            'kpis' => [
                'total_vendido' => round((float) $emitidas->sum('total'), 2),
                'ventas' => $emitidas->count(),
                'contado' => round((float) $emitidas->reject(fn (Sale $sale) => $sale->esCredito())->sum('total'), 2),
                'credito' => round((float) $emitidas->filter(fn (Sale $sale) => $sale->esCredito())->sum('total'), 2),
                // Lo que falta enviar a SUNAT no depende del rango: tiene plazo.
                'por_enviar' => ElectronicDocument::query()
                    ->where('sunat_estado', 'por_enviar')
                    ->whereHas('sale', fn ($query) => $query->where('vendedor_id', $vendedorId))
                    ->count(),
                'borradores' => Sale::query()->where('vendedor_id', $vendedorId)->where('estado', 'borrador')->count(),
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
     * Único punto para editar una venta: el mismo formulario de nueva venta,
     * ya lleno. Sirve para un borrador, una nota de venta y una factura o
     * boleta que SUNAT aún no recibe o que rechazó. Al guardar conserva su
     * número. Una aceptada por SUNAT se corrige con nota de crédito.
     */
    public function edit(Team $current_team, Sale $sale, Request $request): Response|RedirectResponse
    {
        $this->assertSedeAccess($request, $sale);
        $sale->load('electronicDocuments');

        if (! $sale->sePuedeEditar()) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $sale->estado === 'anulada'
                ? "La venta {$sale->numero_interno} está anulada: usa «Rehacer venta»."
                : "La venta {$sale->numero_interno} ya fue aceptada por SUNAT: corrígela con una nota de crédito."]);

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

    public function update(Team $current_team, Sale $sale, StoreSaleRequest $request, CreateSale $createSale, ConfirmSale $confirmSale, EditarVentaEmitida $editarVentaEmitida): RedirectResponse
    {
        $this->assertSedeAccess($request, $sale);

        $data = $request->safe()->except(['items', 'emitir']);
        $items = $request->safe()->input('items');

        if ($sedeId = $request->user()->sedeRestringidaId()) {
            $data['sede_id'] = $sedeId;
        }

        if ($sale->estado !== 'borrador') {
            $sale = $editarVentaEmitida->handle($sale, $data, $items, $request->user()->id);
            $numero = $sale->numeroComprobante() ?? $sale->numero_interno;

            Inertia::flash('toast', ['type' => 'success', 'message' => "Venta {$sale->numero_interno} corregida: {$numero}."]);

            return redirect()->route('vendedor.ventas.show', ['current_team' => $current_team, 'sale' => $sale]);
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
     * Datos de una venta para llenar el formulario: para editar su borrador
     * o, como copia, para rehacerla después de anularla.
     *
     * @return array<string, mixed>|null
     */
    protected function ventaParaFormulario(int $saleId, ?int $sedeId, bool $comoCopia): ?array
    {
        $sale = Sale::query()
            ->with('client', 'items.product', 'items.service', 'items.inventoryUnit', 'installments', 'payments', 'electronicDocuments')
            ->when($sedeId, fn ($query) => $query->where('sede_id', $sedeId))
            ->when(request()->user()?->vendedorRestringidoId(), fn ($query, int $vendedorId) => $query->where('vendedor_id', $vendedorId))
            ->find($saleId);

        if (! $sale) {
            return null;
        }

        // Al emitirse, el medio de pago pasa al cobro registrado.
        $medioPago = $sale->medio_pago
            ?? $sale->payments->whereNull('installment_id')->where('forma_pago', '!=', 'deposito')->sortBy('id')->pluck('forma_pago')->first()
            ?? 'efectivo';
        $emitida = ! $comoCopia && $sale->estado === 'confirmada';

        return [
            'id' => $comoCopia ? null : $sale->id,
            'numero_interno' => $sale->numero_interno,
            'emitida' => $emitida,
            'comprobante' => $emitida ? $sale->numeroComprobante() : null,
            'rechazado' => $emitida && (bool) $sale->comprobanteElectronico()?->fueRechazado(),
            'fecha' => $emitida ? $sale->fecha->toDateString() : null,
            'client' => $sale->client->only(['id', 'tipo_documento', 'razon_social', 'numero_documento']),
            'sede_id' => $sale->sede_id,
            'destino' => $sale->destino,
            'referencia' => $sale->referencia,
            'condicion_pago' => $sale->esCredito() ? 'credito' : 'contado',
            'medio_pago' => $medioPago,
            'numero_operacion' => $sale->numero_operacion
                ?? $sale->payments->whereNull('installment_id')->where('forma_pago', $medioPago)->pluck('numero_operacion')->first(),
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

        $sedeId = $request->user()->sedeRestringidaId();
        if ($sedeId) {
            $data['sede_id'] = $sedeId;
        }

        // Emitir en el mismo paso: si algo falla (RUC, stock), no queda nada
        // a medias y el error sale en el formulario.
        $emitir = $request->boolean('emitir');

        $serviceOrderId = $request->integer('service_order_id') ?: null;
        $sale = DB::transaction(function () use ($data, $items, $request, $createSale, $emitir, $serviceOrderId, $numberGenerator, $sedeId) {
            // Solo órdenes y cotizaciones de la sede del vendedor.
            $serviceOrder = $serviceOrderId ? ServiceOrder::lockForUpdate()->when($sedeId, fn ($query) => $query->where('sede_id', $sedeId))->find($serviceOrderId) : null;
            if ($serviceOrderId && ! $serviceOrder) {
                throw ValidationException::withMessages(['service_order_id' => 'La orden de servicio no es de tu sede.']);
            }
            if ($serviceOrder?->sale_id) {
                throw ValidationException::withMessages(['service_order_id' => 'Esta orden ya fue cobrada.']);
            }
            if ($serviceOrder && $serviceOrder->client_id !== (int) $data['client_id']) {
                throw ValidationException::withMessages(['client_id' => 'La venta debe pertenecer al cliente de la orden.']);
            }
            $sale = $createSale->handle($data, $items, $request->user()->id);
            $serviceOrder?->update(['sale_id' => $sale->id]);

            if (! $serviceOrder && ! empty($data['quote_id'])) {
                $quote = Quote::query()->with(['items.service', 'equipments'])->when($sedeId, fn ($query) => $query->where('sede_id', $sedeId))->find((int) $data['quote_id']);
                if (! $quote) {
                    throw ValidationException::withMessages(['quote_id' => 'La cotización no es de tu sede.']);
                }
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
            'editable' => $sale->sePuedeEditar(),
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

        return response(app(ComprobantePdfService::class)->notaDeVenta($sale), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "inline; filename=\"{$sale->numero_nota_venta}.pdf\"",
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
        ]);
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
        $this->exigirCajaParaDevolverEfectivo($sale);

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
        $this->exigirCajaParaDevolverEfectivo($sale);
        $descartar->handle($sale);

        Inertia::flash('toast', ['type' => 'success', 'message' => $esBorrador
            ? "Borrador {$sale->numero_interno} descartado; las unidades volvieron al stock."
            : "Nota de venta {$sale->numero_nota_venta} anulada; las unidades volvieron al stock."]);

        return back();
    }

    /**
     * Anular una venta cobrada en efectivo devuelve ese dinero: sale de la
     * caja abierta del vendedor, o no quedaría registrado en ningún arqueo.
     */
    protected function exigirCajaParaDevolverEfectivo(Sale $sale): void
    {
        $efectivo = (float) $sale->payments()->where('forma_pago', 'efectivo')->sum('monto')
            - (float) $sale->refunds()->where('forma_pago', 'efectivo')->sum('monto');

        if (round($efectivo, 2) > 0 && ! CashRegister::abiertaDe((int) $sale->vendedor_id)) {
            throw ValidationException::withMessages([
                'caja' => 'Esta venta se cobró en efectivo: abre la caja del vendedor para registrar la devolución y luego anúlala.',
            ]);
        }
    }

    /**
     * Un trabajador con sede asignada solo opera las ventas de su sede.
     */
    protected function assertSedeAccess(Request $request, Sale $sale): void
    {
        $sedeId = $request->user()->sedeRestringidaId();

        abort_if($sedeId !== null && (int) $sale->sede_id !== $sedeId, 404);

        // Cada vendedor corrige solo sus ventas; el Gerente, las de todos.
        $vendedorId = $request->user()->vendedorRestringidoId();
        abort_if($vendedorId !== null && (int) $sale->vendedor_id !== $vendedorId, 404);
    }
}
