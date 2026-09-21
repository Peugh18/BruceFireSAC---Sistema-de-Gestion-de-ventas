<?php

namespace App\Http\Controllers\Vendedor;

use App\Actions\Sales\ConfirmSale;
use App\Actions\Sales\CreateSale;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\StoreSaleRequest;
use App\Models\Client;
use App\Models\Sale;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SaleController extends Controller
{
    public function index(Team $current_team, Request $request): Response
    {
        $estado = $request->string('estado')->toString();
        $vendedorId = $request->user()->id;

        $sales = Sale::query()
            ->with('client')
            ->when($estado !== '' && $estado !== 'todas', fn ($query) => $query->where('estado', $estado))
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Sale $sale) => [
                'id' => $sale->id,
                'numero_interno' => $sale->numero_interno,
                'cliente' => $sale->client->razon_social,
                'fecha' => $sale->fecha->toDateString(),
                'comprobante_tipo' => $sale->comprobante_tipo,
                'total' => $sale->total,
                'estado' => $sale->estado,
            ]);

        return Inertia::render('vendedor/ventas/index', [
            'sales' => $sales,
            'filters' => ['estado' => $estado],
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

    public function create(Team $current_team): Response
    {
        return Inertia::render('vendedor/ventas/nueva', [
            'clients' => Client::query()
                ->orderByDesc('created_at')
                ->limit(10)
                ->get(['id', 'razon_social', 'numero_documento']),
        ]);
    }

    public function store(Team $current_team, StoreSaleRequest $request, CreateSale $createSale): RedirectResponse
    {
        $data = $request->safe()->except('items');
        $items = $request->safe()->input('items');

        $sale = $createSale->handle($data, $items, $request->user()->id);

        return redirect()->route('vendedor.ventas.show', [
            'current_team' => $current_team,
            'sale' => $sale,
        ]);
    }

    public function show(Team $current_team, Sale $sale): Response
    {
        return Inertia::render('vendedor/ventas/show', [
            'sale' => $sale->load('items.catalogItem', 'client', 'payments', 'electronicDocuments'),
        ]);
    }

    public function confirm(Team $current_team, Sale $sale, ConfirmSale $confirmSale): RedirectResponse
    {
        $confirmSale->handle($sale);

        return redirect()->route('vendedor.ventas.show', [
            'current_team' => $current_team,
            'sale' => $sale,
        ]);
    }
}
