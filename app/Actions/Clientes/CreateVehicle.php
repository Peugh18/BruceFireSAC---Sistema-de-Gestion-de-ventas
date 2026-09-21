<?php

namespace App\Actions\Clientes;

use App\Models\Client;
use App\Models\Vehicle;

class CreateVehicle
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Client $client, array $data): Vehicle
    {
        return $client->vehicles()->create($data);
    }
}
