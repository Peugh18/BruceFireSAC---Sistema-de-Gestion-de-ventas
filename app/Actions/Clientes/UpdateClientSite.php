<?php

namespace App\Actions\Clientes;

use App\Models\ClientSite;

class UpdateClientSite
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(ClientSite $site, array $data): ClientSite
    {
        $site->update($data);

        return $site;
    }
}
