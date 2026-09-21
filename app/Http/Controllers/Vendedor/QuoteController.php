<?php

namespace App\Http\Controllers\Vendedor;

use App\Actions\Cotizaciones\CreateQuote;
use App\Actions\Cotizaciones\TransitionQuoteState;
use App\Http\Controllers\Controller;
use App\Http\Requests\Cotizaciones\StoreQuoteRequest;
use App\Models\Quote;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class QuoteController extends Controller
{
    public function index(Request $request): Response
    {
        $estado = $request->string('estado')->toString();

        $quotes = Quote::query()
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
                'activas' => Quote::whereIn('estado', ['borrador', 'emitida', 'enviada'])->count(),
                'por_vencer' => Quote::where('estado', 'enviada')
                    ->whereBetween('vigencia_hasta', [now(), now()->addDays(7)])
                    ->count(),
                'aceptadas_este_mes' => Quote::where('estado', 'aceptada')
                    ->whereBetween('updated_at', [now()->startOfMonth(), now()->endOfMonth()])
                    ->count(),
                'vencidas' => Quote::where('estado', 'vencida')->count(),
            ],
        ]);
    }

    public function store(StoreQuoteRequest $request, CreateQuote $createQuote): RedirectResponse
    {
        $data = $request->safe()->except('items');
        $items = $request->safe()->input('items');

        $createQuote->handle($data, $items, $request->user()->id);

        return back();
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
    public function send(Team $current_team, Quote $quote): RedirectResponse
    {
        app(TransitionQuoteState::class)->handle($quote, 'enviada');

        return back();
    }

    public function accept(Team $current_team, Quote $quote): RedirectResponse
    {
        app(TransitionQuoteState::class)->handle($quote, 'aceptada');

        return back();
    }

    public function reject(Team $current_team, Quote $quote): RedirectResponse
    {
        app(TransitionQuoteState::class)->handle($quote, 'rechazada');

        return back();
    }
}
