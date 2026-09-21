<?php

namespace App\Actions\Clientes;

use App\Models\Client;
use App\Models\ClientSite;

class CreateClientSite
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Client $client, array $data): ClientSite
    {
        return $client->sites()->create($data);
    }
}
