<?php

namespace App\Http\Controllers\Vendedor;

use App\Actions\Billing\AnularVentaPorEnviar;
use App\Actions\Billing\CorregirComprobante;
use App\Actions\Billing\EmitElectronicDocument;
use App\Actions\Sales\ConfirmSale;
use App\Actions\Sales\CreateSale;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\CorregirComprobanteRequest;
use App\Http\Requests\Sales\StoreSaleRequest;
use App\Models\Client;
use App\Models\CompanySetting;
use App\Models\Quote;
use App\Models\QuoteItem;
use App\Models\Sale;
use App\Models\Sede;
use App\Models\Team;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
            ->with('client')
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
            'clients' => Client::query()
                ->orderByDesc('created_at')
                ->limit(10)
                ->get(['id', 'tipo_documento', 'razon_social', 'numero_documento']),
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
        ]);
    }

    /**
     * Cotización aceptada que se pasa a venta: precarga el cliente y
     * muestra los ítems cotizados para escanear sus series.
     *
     * @return array{id: int, numero: string, client: array{id: int, tipo_documento: string, razon_social: string, numero_documento: string}, items: list<array{tipo: string, product_id: int|null, service_id: int|null, nombre: string, cantidad: int, precio_unitario: float}>}|null
     */
    protected function cotizacionParaVenta(int $quoteId, ?int $sedeId): ?array
    {
        $quote = Quote::query()
            ->with('client', 'items.product', 'items.service')
            ->where('estado', 'aceptada')
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
                'cantidad' => (int) $item->cantidad,
                'precio_unitario' => (float) $item->precio_unitario,
            ])->values()->all(),
        ];
    }

    public function store(Team $current_team, StoreSaleRequest $request, CreateSale $createSale): RedirectResponse
    {
        $data = $request->safe()->except('items');
        $items = $request->safe()->input('items');

        if ($sedeId = $request->user()->sedeRestringidaId()) {
            $data['sede_id'] = $sedeId;
        }

        $sale = $createSale->handle($data, $items, $request->user()->id);

        return redirect()->route('vendedor.ventas.show', [
            'current_team' => $current_team,
            'sale' => $sale,
        ]);
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
     * Un trabajador con sede asignada solo opera las ventas de su sede.
     */
    protected function assertSedeAccess(Request $request, Sale $sale): void
    {
        $sedeId = $request->user()->sedeRestringidaId();

        abort_if($sedeId !== null && (int) $sale->sede_id !== $sedeId, 404);
    }
}
