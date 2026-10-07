<?php

namespace App\Http\Controllers\Gerente;

use App\Http\Controllers\Controller;
use App\Models\Driver;
use App\Models\Team;
use App\Models\TransportVehicle;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Datos maestros del transporte privado de las guías de remisión: vehículos
 * de la empresa (placa y categoría M1, L o N) y conductores (DNI y licencia).
 */
class TransporteController extends Controller
{
    public function index(Team $current_team): Response
    {
        return Inertia::render('gerente/transporte/index', [
            'vehiculos' => TransportVehicle::query()->orderBy('placa')->get(),
            'conductores' => Driver::query()->orderBy('apellidos')->get(),
            'categorias' => TransportVehicle::CATEGORIAS,
        ]);
    }

    public function storeVehiculo(Team $current_team, Request $request): RedirectResponse
    {
        $request->merge(['placa' => mb_strtoupper(preg_replace('/[\s-]+/', '', (string) $request->input('placa')) ?? '')]);
        $datos = $request->validate([
            'placa' => ['required', 'string', 'max:10', 'unique:transport_vehicles,placa'],
            'categoria' => ['required', Rule::in(array_keys(TransportVehicle::CATEGORIAS))],
            'descripcion' => ['nullable', 'string', 'max:255'],
        ]);

        TransportVehicle::create($datos);

        return back()->with('success', 'Vehículo registrado.');
    }

    public function storeConductor(Team $current_team, Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'dni' => ['required', 'digits:8', 'unique:drivers,dni'],
            'nombres' => ['required', 'string', 'max:255'],
            'apellidos' => ['required', 'string', 'max:255'],
            'licencia' => ['required', 'string', 'max:20'],
        ]);

        Driver::create($datos);

        return back()->with('success', 'Conductor registrado.');
    }

    public function toggleVehiculo(Team $current_team, TransportVehicle $vehiculo): RedirectResponse
    {
        $vehiculo->update(['activo' => ! $vehiculo->activo]);

        return back()->with('success', $vehiculo->activo ? 'Vehículo activado.' : 'Vehículo desactivado.');
    }

    public function toggleConductor(Team $current_team, Driver $conductor): RedirectResponse
    {
        $conductor->update(['activo' => ! $conductor->activo]);

        return back()->with('success', $conductor->activo ? 'Conductor activado.' : 'Conductor desactivado.');
    }
}
