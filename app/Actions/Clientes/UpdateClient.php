<?php

namespace App\Actions\Clientes;

use App\Models\Client;

class UpdateClient
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Client $client, array $data): Client
    {
        $client->update($data);

        return $client;
    }
}
