<?php

namespace App\Http\Controllers\Vendedor;

use App\Actions\Cotizaciones\CreateQuote;
use App\Actions\Cotizaciones\TransitionQuoteState;
use App\Http\Controllers\Controller;
use App\Http\Requests\Cotizaciones\StoreQuoteRequest;
use App\Models\Product;
use App\Models\Quote;
use App\Models\Service;
use App\Models\Team;
use App\Services\Quotes\QuotePdfService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class QuoteController extends Controller
{
    public function index(Request $request, QuotePdfService $pdf): Response
    {
        $estado = $request->string('estado')->toString();
        $sedeId = $request->user()->sedeRestringidaId();
        $porSede = fn ($query) => $query->when($sedeId, fn ($q) => $q->where('sede_id', $sedeId));

        $quotes = $porSede(Quote::query())
            ->with('client', 'sale')
            ->when($estado !== '' && $estado !== 'todas', function ($query) use ($estado) {
                return $estado === 'enviada'
                    ? $query->whereIn('estado', ['emitida', 'enviada', 'pendiente'])
                    : $query->where('estado', $estado);
            })
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Quote $quote) => [
                'id' => $quote->id,
                'numero' => $quote->numero,
                'cliente' => $quote->client->razon_social,
                'total' => $quote->total,
                'vigencia_hasta' => $quote->vigencia_hasta->toDateString(),
                'estado' => $quote->estado,
                'whatsapp' => $this->numeroWhatsapp($quote->client->whatsapp ?: $quote->client->telefono),
                'enlace_pdf' => $pdf->enlacePublico($quote),
                'venta' => $quote->sale ? ['id' => $quote->sale->id, 'numero' => $quote->sale->numero_interno] : null,
            ]);

        return Inertia::render('vendedor/cotizaciones/index', [
            'quotes' => $quotes,
            'filters' => ['estado' => $estado],
            'kpis' => [
                'activas' => $porSede(Quote::query())->whereIn('estado', ['borrador', 'emitida', 'enviada', 'aceptada'])->count(),
                'por_vencer' => $porSede(Quote::query())->where('estado', 'enviada')
                    ->whereBetween('vigencia_hasta', [now(), now()->addDays(7)])
                    ->count(),
                'aceptadas_este_mes' => $porSede(Quote::query())->whereIn('estado', ['aceptada', 'convertida'])
                    ->whereBetween('updated_at', [now()->startOfMonth(), now()->endOfMonth()])
                    ->count(),
                'vencidas' => $porSede(Quote::query())->where('estado', 'vencida')->count(),
            ],
        ]);
    }

    public function create(Team $current_team, Request $request): Response
    {
        $quote = null;

        if ($request->filled('renovar')) {
            $quote = Quote::query()
                ->with('client', 'items.product', 'items.service')
                ->where('estado', 'vencida')
                ->when($request->user()->sedeRestringidaId(), fn ($query, $sedeId) => $query->where('sede_id', $sedeId))
                ->find($request->integer('renovar'));
        }

        return Inertia::render('vendedor/cotizaciones/nueva', [
            'renovacion' => $quote ? [
                'numero' => $quote->numero,
                'client' => $quote->client->only(['id', 'tipo_documento', 'razon_social', 'numero_documento']),
                'referencia' => $quote->referencia,
                'condicion_pago_propuesta' => $quote->condicion_pago_propuesta,
                'observaciones' => $quote->observaciones,
                'items' => $quote->items->map(fn ($item) => [
                    'tipo' => $item->product_id ? 'product' : 'service',
                    'product_id' => $item->product_id,
                    'service_id' => $item->service_id,
                    'codigo' => $item->product?->codigo ?? $item->service?->codigo,
                    'nombre' => $item->product?->nombre ?? $item->service?->nombre,
                    'cantidad' => (int) $item->cantidad,
                    'precio_unitario' => (float) $item->precio_unitario,
                    'descuento' => (float) $item->descuento,
                ])->values(),
            ] : null,
        ]);
    }

    /**
     * Búsqueda liviana de productos + servicios para el selector de ítems
     * de Nueva Cotización (ver nota en create() sobre por qué no se manda
     * el catálogo completo como prop).
     */
    public function searchCatalogo(Request $request): JsonResponse
    {
        $search = $request->string('search')->toString();

        if (mb_strlen($search) < 2) {
            return response()->json([]);
        }

        $products = Product::query()
            ->where('activo', true)
            ->where('nombre', 'like', "%{$search}%")
            ->orderBy('nombre')
            ->limit(10)
            ->get(['id', 'nombre', 'precio_venta'])
            ->map(fn (Product $product) => [...$product->only(['id', 'nombre', 'precio_venta']), 'tipo' => 'product']);

        $services = Service::query()
            ->where('activo', true)
            ->where('nombre', 'like', "%{$search}%")
            ->orderBy('nombre')
            ->limit(10)
            ->get(['id', 'nombre', 'precio_venta'])
            ->map(fn (Service $service) => [...$service->only(['id', 'nombre', 'precio_venta']), 'tipo' => 'service']);

        return response()->json($products->concat($services)->values());
    }

    public function store(StoreQuoteRequest $request, CreateQuote $createQuote): RedirectResponse
    {
        $data = $request->safe()->except('items');
        $items = $request->safe()->input('items');

        if ($sedeId = $request->user()->sedeRestringidaId()) {
            $data['sede_id'] = $sedeId;
        }

        $quote = $createQuote->handle($data, $items, $request->user()->id);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Cotización {$quote->numero} creada. Pulsa Enviar cuando se la mandes al cliente."]);

        return redirect()->route('vendedor.cotizaciones.index', [
            'current_team' => $request->route('current_team'),
        ]);
    }

    /**
     * Nota: {current_team} es un segmento de ruta que precede a {quote} en
     * routes/web.php (grupo compartido con todo el panel autenticado). El
     * dispatcher de Laravel pasa los parámetros de ruta POR POSICIÓN a menos
     * que cada uno tenga un parámetro de método correspondiente — por eso
     * $current_team se declara aquí (con ese nombre exacto, para el binding
     * implícito) aunque no se use, en vez de dejar que "current_team" se
     * cuele posicionalmente en el lugar de $quote.
     */
    /**
     * La lista solo ofrece "Enviar": un borrador se emite y se envía en el
     * mismo paso, respetando la secuencia borrador → emitida → enviada.
     */
    /**
     * PDF de la cotización con la marca: se abre para ver o imprimir, o se
     * descarga para adjuntarlo.
     */
    public function pdf(Team $current_team, Quote $quote, Request $request, QuotePdfService $pdf): HttpResponse
    {
        $sedeId = $request->user()->sedeRestringidaId();
        abort_if($sedeId !== null && (int) $quote->sede_id !== $sedeId, 404);

        $documento = $pdf->generate($quote);

        return $request->boolean('descargar')
            ? $documento->download($pdf->nombreArchivo($quote))
            : $documento->stream($pdf->nombreArchivo($quote));
    }

    /**
     * Número para wa.me: solo dígitos y con el 51 de Perú si es un celular
     * de 9 dígitos.
     */
    protected function numeroWhatsapp(?string $telefono): ?string
    {
        $digitos = preg_replace('/\D/', '', (string) $telefono);

        if ($digitos === '') {
            return null;
        }

        return strlen($digitos) === 9 ? '51'.$digitos : $digitos;
    }

    public function send(Team $current_team, Quote $quote): RedirectResponse
    {
        $transitionQuoteState = app(TransitionQuoteState::class);

        DB::transaction(function () use ($quote, $transitionQuoteState) {
            if ($quote->estado === 'borrador') {
                $transitionQuoteState->handle($quote, 'emitida');
            }

            $transitionQuoteState->handle($quote, 'enviada');
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => "Cotización {$quote->numero} enviada al cliente."]);

        return back();
    }

    public function accept(Team $current_team, Quote $quote): RedirectResponse
    {
        app(TransitionQuoteState::class)->handle($quote, 'aceptada');

        Inertia::flash('toast', ['type' => 'success', 'message' => "Cotización {$quote->numero} aceptada. Ya puedes pasarla a venta."]);

        return back();
    }

    public function reject(Team $current_team, Quote $quote): RedirectResponse
    {
        app(TransitionQuoteState::class)->handle($quote, 'rechazada');

        Inertia::flash('toast', ['type' => 'success', 'message' => "Cotización {$quote->numero} rechazada."]);

        return back();
    }
}
