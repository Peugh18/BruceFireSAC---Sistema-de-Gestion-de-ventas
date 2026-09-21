<?php

namespace App\Http\Controllers\Vendedor;

use App\Actions\Clientes\CreateVehicle;
use App\Actions\Clientes\UpdateVehicle;
use App\Http\Controllers\Controller;
use App\Http\Requests\Clientes\StoreVehicleRequest;
use App\Http\Requests\Clientes\UpdateVehicleRequest;
use App\Models\Client;
use App\Models\Team;
use App\Models\Vehicle;
use Illuminate\Http\RedirectResponse;

class VehicleController extends Controller
{
    // Nota: $current_team se declara primero en cada método porque {current_team}
    // precede a los demás segmentos en la ruta y el dispatcher de Laravel pasa
    // los parámetros de ruta por posición (ver ClientController::show).
    public function store(Team $current_team, StoreVehicleRequest $request, Client $client, CreateVehicle $createVehicle): RedirectResponse
    {
        $createVehicle->handle($client, $request->validated());

        return back();
    }

    public function update(Team $current_team, UpdateVehicleRequest $request, Client $client, Vehicle $vehicle, UpdateVehicle $updateVehicle): RedirectResponse
    {
        abort_if($vehicle->client_id !== $client->id, 404);

        $updateVehicle->handle($vehicle, $request->validated());

        return back();
    }

    public function destroy(Team $current_team, Client $client, Vehicle $vehicle): RedirectResponse
    {
        abort_if($vehicle->client_id !== $client->id, 404);

        $vehicle->delete();

        return back();
    }
}
