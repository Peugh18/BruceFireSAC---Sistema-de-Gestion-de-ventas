<?php

namespace App\Http\Controllers\Vendedor;

use App\Actions\Clientes\CreateClientSite;
use App\Actions\Clientes\UpdateClientSite;
use App\Http\Controllers\Controller;
use App\Http\Requests\Clientes\StoreClientSiteRequest;
use App\Http\Requests\Clientes\UpdateClientSiteRequest;
use App\Models\Client;
use App\Models\ClientSite;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;

class ClientSiteController extends Controller
{
    // Nota: $current_team se declara primero en cada método porque {current_team}
    // precede a los demás segmentos en la ruta y el dispatcher de Laravel pasa
    // los parámetros de ruta por posición (ver ClientController::show).
    public function store(Team $current_team, StoreClientSiteRequest $request, Client $client, CreateClientSite $createClientSite): RedirectResponse
    {
        $createClientSite->handle($client, $request->validated());

        return back();
    }

    public function update(Team $current_team, UpdateClientSiteRequest $request, Client $client, ClientSite $site, UpdateClientSite $updateClientSite): RedirectResponse
    {
        abort_if($site->client_id !== $client->id, 404);

        $updateClientSite->handle($site, $request->validated());

        return back();
    }

    public function destroy(Team $current_team, Client $client, ClientSite $site): RedirectResponse
    {
        abort_if($site->client_id !== $client->id, 404);

        $site->delete();

        return back();
    }
}
