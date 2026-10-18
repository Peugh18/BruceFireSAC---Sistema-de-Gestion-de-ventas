<?php

namespace App\Http\Controllers\TecnicoCampo;

use App\Actions\TecnicoCampo\IniciarRecojo;
use App\Http\Controllers\Controller;
use App\Http\Requests\TecnicoCampo\IniciarRecojoRequest;
use App\Models\Client;
use App\Models\Sede;
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

        return response()->json($client->only([
            'id', 'tipo_documento', 'numero_documento', 'razon_social', 'nombre_comercial', 'telefono', 'whatsapp', 'direccion_fiscal',
        ]));
    }
}
