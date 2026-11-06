<?php

namespace App\Http\Controllers\TecnicoCampo;

use App\Actions\Clientes\CreateClient;
use App\Actions\Clientes\CreateClientSite;
use App\Actions\TecnicoCampo\IniciarRecojo;
use App\Http\Controllers\Controller;
use App\Http\Requests\Clientes\StoreClientRequest;
use App\Http\Requests\Clientes\StoreClientSiteRequest;
use App\Http\Requests\TecnicoCampo\IniciarRecojoRequest;
use App\Models\Client;
use App\Models\Sede;
use App\Models\ServiceOrder;
use App\Models\Team;
use App\Support\DatosPeru;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class IniciarRecojoController extends Controller
{
    public function create(Team $current_team, Request $request): Response
    {
        $sedeIds = $request->user()->sede_id
            ? Sede::idsComercialesAtendidasPor((int) $request->user()->sede_id)
            : [];

        return Inertia::render('tecnico-campo/recojos/create', [
            'sedes' => Sede::query()->with('almacen:id,nombre')->whereIn('id', $sedeIds)->where('activo', true)->orderBy('nombre')->get(['id', 'nombre', 'tipo', 'almacen_id'])->map(fn (Sede $sede): array => [
                'id' => $sede->id,
                'nombre' => $sede->nombre,
                'almacen' => $sede->almacen?->only(['id', 'nombre']),
            ])->values(),
        ]);
    }

    public function store(Team $current_team, IniciarRecojoRequest $request, IniciarRecojo $action): RedirectResponse
    {
        $order = $action->handle($request->validated(), $request->user());

        return redirect()->route('tecnico-campo.recojos.show', [
            'current_team' => $current_team,
            'service_order' => $order,
        ])->with('success', 'Recojo iniciado. Registra los extintores y la evidencia antes de confirmar la salida a Planta.');
    }

    /**
     * Registra al cliente con el alta completa de Clientes (misma validación
     * y misma acción que el vendedor), para elegirlo en el recojo.
     */
    public function storeClient(StoreClientRequest $request, CreateClient $createClient): JsonResponse
    {
        $client = $createClient->handle($request->validated());

        return response()->json($client->only(['id', 'tipo_documento', 'razon_social', 'numero_documento']), 201);
    }

    /**
     * Agrega un local al cliente (misma validación y acción que el vendedor):
     * su dirección sale en la constancia y en el certificado.
     */
    public function storeLocal(Team $current_team, Client $client, StoreClientSiteRequest $request, CreateClientSite $createClientSite): JsonResponse
    {
        // Mismo filtro que al iniciar el recojo: sin clientes inactivos ni "varios".
        abort_if(! $client->activo || $client->esClientesVarios(), 422, 'Elige un cliente activo con identidad propia.');

        $local = $createClientSite->handle($client, $request->validated());

        return response()->json($local->only(['id', 'nombre', 'direccion']), 201);
    }

    public function search(Request $request): JsonResponse
    {
        $search = trim($request->validate(['search' => ['required', 'string', 'max:255']])['search']);
        if ($search === '') {
            return response()->json([]);
        }

        $telefono = DatosPeru::telefono($search);
        $clients = Client::query()
            ->where('activo', true)
            ->where(function ($query): void {
                $query->whereNull('tipo_documento')->orWhere('tipo_documento', '!=', Client::TIPO_DOCUMENTO_VARIOS);
            })
            ->where(function ($query) use ($search, $telefono): void {
                $query->buscar($search);
                if ($telefono !== null) {
                    $query->orWhere('telefono', $telefono)
                        ->orWhere('whatsapp', $telefono);
                }
            })
            ->orderBy('razon_social')
            ->limit(10)
            ->get(['id', 'tipo_documento', 'numero_documento', 'razon_social', 'nombre_comercial', 'telefono', 'direccion_fiscal']);

        return response()->json($clients);
    }

    public function ficha(Team $current_team, Client $client): JsonResponse
    {
        abort_unless($client->activo && ! $client->esClientesVarios(), 404);

        // La misma ficha del vendedor: con sus locales, de donde sale el lugar del recojo.
        // La tienda que cobra el recojo: donde el cliente compró o pidió su última orden.
        $sedeHabitual = $client->sales()->latest('id')->value('sede_id')
            ?? ServiceOrder::query()->where('client_id', $client->id)->latest('id')->value('sede_id');

        return response()->json([...$client->ficha(), 'whatsapp' => $client->whatsapp, 'sede_habitual' => $sedeHabitual]);
    }
}
