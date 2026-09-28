<?php

use App\Models\Client;
use App\Models\CompanySetting;
use App\Models\Sede;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('el catalogo del INEI esta cargado y los datos de la direccion salen del ubigeo', function () {
    $client = Client::factory()->create(['ubigeo' => '130101']);
    $sede = Sede::factory()->create(['ubigeo' => '130101']);
    $empresa = CompanySetting::factory()->create(['ubigeo' => '150501']);

    expect($client->departamento)->toBe('LA LIBERTAD')
        ->and($client->provincia)->toBe('TRUJILLO')
        ->and($client->distrito)->toBe('TRUJILLO')
        ->and($sede->ciudad)->toBe('Trujillo')
        ->and($empresa->distrito)->toBe('SAN VICENTE DE CAÑETE')
        ->and($empresa->provincia)->toBe('CAÑETE');
});

test('la base de datos rechaza un ubigeo que no existe en el catalogo', function () {
    Client::factory()->create(['ubigeo' => '999999']);
})->throws(QueryException::class);

test('el formulario de cliente rechaza un ubigeo inventado', function () {
    $vendedor = User::factory()->create();
    $vendedor->assignRole('Vendedor');

    $this->actingAs($vendedor)
        ->post(route('vendedor.clientes.store', ['current_team' => $vendedor->currentTeam]), [
            'tipo_documento' => 'dni',
            'numero_documento' => '44556677',
            'razon_social' => 'CLIENTE PRUEBA',
            'ubigeo' => '999999',
        ])
        ->assertSessionHasErrors('ubigeo');
});

test('el buscador encuentra distritos por nombre y codigo', function () {
    $vendedor = User::factory()->create();

    $this->actingAs($vendedor)
        ->getJson(route('ubigeos.buscar', ['search' => 'trujillo libertad']))
        ->assertOk()
        ->assertJsonFragment(['codigo' => '130101', 'etiqueta' => 'TRUJILLO - TRUJILLO - LA LIBERTAD']);

    $this->actingAs($vendedor)
        ->getJson(route('ubigeos.buscar', ['search' => '150501']))
        ->assertOk()
        ->assertJsonCount(1)
        ->assertJsonPath('0.distrito', 'SAN VICENTE DE CAÑETE');
});

test('la consulta SUNAT completa el ubigeo del cliente solo si existe en el catalogo', function () {
    config(['services.apisperu.token' => 'token-de-prueba']);
    Http::preventStrayRequests();
    Http::fake([
        'dniruc.apisperu.com/api/v1/ruc/20111111111*' => Http::response(['razonSocial' => 'UNO SAC', 'ubigeo' => '130101']),
        'dniruc.apisperu.com/api/v1/ruc/20222222222*' => Http::response(['razonSocial' => 'DOS SAC', 'ubigeo' => '999999']),
    ]);

    $vendedor = User::factory()->create();
    $vendedor->assignRole('Vendedor');
    $valido = Client::factory()->create(['tipo_documento' => 'ruc', 'numero_documento' => '20111111111', 'ubigeo' => null]);
    $invalido = Client::factory()->create(['tipo_documento' => 'ruc', 'numero_documento' => '20222222222', 'ubigeo' => null]);

    foreach ([$valido, $invalido] as $client) {
        $this->actingAs($vendedor)
            ->post(route('vendedor.clientes.verificar-sunat', ['current_team' => $vendedor->currentTeam, 'client' => $client]))
            ->assertRedirect();
    }

    expect($valido->fresh()->ubigeo)->toBe('130101')
        ->and($invalido->fresh()->ubigeo)->toBeNull();
});
