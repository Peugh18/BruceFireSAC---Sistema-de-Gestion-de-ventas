<?php

namespace App\Http\Controllers\Vendedor;

use App\Actions\Clientes\CreateClient;
use App\Actions\Clientes\UpdateClient;
use App\Http\Controllers\Controller;
use App\Http\Requests\Clientes\StoreClientRequest;
use App\Http\Requests\Clientes\UpdateClientRequest;
use App\Models\Client;
use App\Models\Team;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class ClientController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->string('search')->toString();

        $clients = Client::query()
            ->withMax('sales', 'fecha')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('razon_social', 'like', "%{$search}%")
                        ->orWhere('numero_documento', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Client $client) => [
                'id' => $client->id,
                'codigo_interno' => $client->codigo_interno,
                'cliente' => [
                    'razon_social' => $client->razon_social,
                    'tipo_documento' => $client->tipo_documento,
                    'avatar' => mb_substr($client->razon_social, 0, 1),
                ],
                'documento' => $client->numero_documento,
                'direccion' => $client->direccion_fiscal,
                'estado_sunat' => [
                    'estado_contribuyente' => $client->estado_contribuyente,
                    'condicion_domicilio' => $client->condicion_domicilio,
                ],
                'ultima_compra' => $client->sales_max_fecha
                    ? Carbon::parse($client->sales_max_fecha)->toDateString()
                    : null,
                'activo' => $client->activo,
            ]);

        return Inertia::render('vendedor/clientes/index', [
            'clients' => $clients,
            'filters' => [
                'search' => $search,
            ],
            'columns' => ['Cliente', 'RUC/DNI', 'Direccion', 'Estado SUNAT', 'Ultima compra', 'Acciones'],
            'kpis' => [
                'total_clientes' => Client::count(),
                'nuevos_este_mes' => Client::whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])->count(),
                'porcentaje_activo_habido' => $this->activeAndLocatedPercentage(),
                'inactivos' => Client::where('activo', false)->count(),
            ],
        ]);
    }

    /**
     * Búsqueda liviana de clientes para selectores de otras pantallas
     * (Nueva Venta, Nueva Cotización) que no pueden mandar la tabla
     * completa de clientes como prop en cada carga de página.
     */
    public function search(Request $request): JsonResponse
    {
        $search = $request->string('search')->toString();

        if (mb_strlen($search) < 2) {
            return response()->json([]);
        }

        $clients = Client::query()
            ->where(function ($query) use ($search) {
                $query->where('razon_social', 'like', "%{$search}%")
                    ->orWhere('numero_documento', 'like', "%{$search}%");
            })
            ->orderBy('razon_social')
            ->limit(10)
            ->get(['id', 'razon_social', 'numero_documento']);

        return response()->json($clients);
    }

    public function store(StoreClientRequest $request, CreateClient $createClient): RedirectResponse
    {
        $client = $createClient->handle($request->validated());

        return redirect()->route('vendedor.clientes.show', [
            'current_team' => $request->route('current_team'),
            'client' => $client,
        ]);
    }

    /**
     * Nota: {current_team} precede a {client}/{quote}/etc. en la ruta, y el
     * dispatcher de Laravel pasa los parámetros de ruta por POSICIÓN salvo
     * que cada uno tenga su propio parámetro de método — por eso se declara
     * $current_team aquí (con ese nombre, para el binding implícito) en vez
     * de dejar que se cuele posicionalmente en el lugar de otro parámetro.
     */
    public function show(Team $current_team, Client $client): Response
    {
        $client->load(['sites', 'vehicles']);

        return Inertia::render('vendedor/clientes/show', [
            'client' => $client,
            'sites' => $client->sites,
            'vehicles' => $client->vehicles,
            'cotizaciones' => [],
            'ventas' => [],
            'servicios' => [],
            'certificados' => [],
            'comprobantes' => [],
            'cobranzas' => [],
            'historial' => [],
        ]);
    }

    public function update(Team $current_team, UpdateClientRequest $request, Client $client, UpdateClient $updateClient): RedirectResponse
    {
        $updateClient->handle($client, $request->validated());

        return back();
    }

    protected function activeAndLocatedPercentage(): float
    {
        $total = Client::count();

        if ($total === 0) {
            return 0.0;
        }

        $activeAndLocated = Client::where('estado_contribuyente', 'ACTIVO')
            ->where('condicion_domicilio', 'HABIDO')
            ->count();

        return round(($activeAndLocated / $total) * 100, 2);
    }
}
