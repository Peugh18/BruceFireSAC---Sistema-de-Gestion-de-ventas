<?php

namespace App\Actions\Clientes;

use App\Models\Vehicle;

class UpdateVehicle
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Vehicle $vehicle, array $data): Vehicle
    {
        $vehicle->update($data);

        return $vehicle;
    }
}
