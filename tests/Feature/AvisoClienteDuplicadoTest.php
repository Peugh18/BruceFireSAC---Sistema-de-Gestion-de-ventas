<?php

use App\Models\Client;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Http;

test('detecta un documento ya registrado usando solo la base local', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Http::preventStrayRequests();
    $vendedor = User::factory()->create();
    $vendedor->assignRole('Vendedor');
    $client = Client::factory()->create([
        'tipo_documento' => 'ruc',
        'numero_documento' => '20601111222',
        'razon_social' => 'CLIENTE YA REGISTRADO S.A.C.',
    ]);

    $this->actingAs($vendedor)
        ->getJson(route('vendedor.clientes.search', [
            'current_team' => $vendedor->currentTeam,
            'search' => '20601111222',
        ]))
        ->assertOk()
        ->assertExactJson([$client->only(['id', 'tipo_documento', 'razon_social', 'numero_documento'])]);

    Http::assertNothingSent();
});
