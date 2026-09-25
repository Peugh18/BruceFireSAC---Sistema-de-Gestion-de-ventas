<?php

namespace App\Http\Controllers\Vendedor;

use App\Actions\Cotizaciones\CreateQuote;
use App\Actions\Cotizaciones\TransitionQuoteState;
use App\Http\Controllers\Controller;
use App\Http\Requests\Cotizaciones\StoreQuoteRequest;
use App\Models\Client;
use App\Models\Product;
use App\Models\Quote;
use App\Models\Service;
use App\Models\Team;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class QuoteController extends Controller
{
    public function index(Request $request): Response
    {
        $estado = $request->string('estado')->toString();
        $sedeId = $request->user()->sedeRestringidaId();
        $porSede = fn ($query) => $query->when($sedeId, fn ($q) => $q->where('sede_id', $sedeId));

        $quotes = $porSede(Quote::query())
            ->with('client')
            ->when($estado !== '' && $estado !== 'todas', fn ($query) => $query->where('estado', $estado))
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
            ]);

        return Inertia::render('vendedor/cotizaciones/index', [
            'quotes' => $quotes,
            'filters' => ['estado' => $estado],
            'kpis' => [
                'activas' => $porSede(Quote::query())->whereIn('estado', ['borrador', 'emitida', 'enviada'])->count(),
                'por_vencer' => $porSede(Quote::query())->where('estado', 'enviada')
                    ->whereBetween('vigencia_hasta', [now(), now()->addDays(7)])
                    ->count(),
                'aceptadas_este_mes' => $porSede(Quote::query())->where('estado', 'aceptada')
                    ->whereBetween('updated_at', [now()->startOfMonth(), now()->endOfMonth()])
                    ->count(),
                'vencidas' => $porSede(Quote::query())->where('estado', 'vencida')->count(),
            ],
        ]);
    }

    public function create(Team $current_team): Response
    {
        return Inertia::render('vendedor/cotizaciones/nueva', [
            // Solo un puñado inicial: con miles de clientes/productos reales,
            // mandar la tabla completa como prop en cada carga de página pesa
            // varios cientos de KB. El resto se busca en el servidor (ver
            // ClientController::search() y self::searchCatalogo()).
            'clients' => Client::query()
                ->orderByDesc('created_at')
                ->limit(10)
                ->get(['id', 'razon_social', 'numero_documento']),
            'products' => Product::query()
                ->where('activo', true)
                ->orderBy('nombre')
                ->limit(8)
                ->get(['id', 'nombre', 'precio_venta']),
            'services' => Service::query()
                ->where('activo', true)
                ->orderBy('nombre')
                ->limit(8)
                ->get(['id', 'nombre', 'precio_venta']),
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
