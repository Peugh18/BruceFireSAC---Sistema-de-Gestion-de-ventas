<?php

namespace App\Actions\Clientes;

use App\Models\Client;
use App\Services\Sunat\RucLookupService;

class UpdateClient
{
    public function __construct(protected RucLookupService $sunat) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Client $client, array $data): Client
    {
        // Con otro documento, su estado SUNAT es el de la nueva consulta.
        if (($data['numero_documento'] ?? $client->numero_documento) !== $client->numero_documento) {
            $data = [...$data, ...CreateClient::estadoSunat($this->sunat, $data)];
        }

        $client->update($data);

        return $client;
    }
}
