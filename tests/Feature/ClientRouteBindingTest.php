<?php

use App\Models\Client;
use App\Models\ClientSite;
use App\Models\Vehicle;
use Database\Seeders\RolesAndPermissionsSeeder;

/**
 * Estas rutas conviven bajo el prefijo {current_team} con más de un
 * parámetro de ruta. El dispatcher de Laravel pasa los parámetros por
 * posición cuando un método no declara explícitamente cada segmento de
 * ruta (ver la nota en ClientController::show) — este archivo existe
 * específicamente para no volver a romper eso en silencio.
 */
beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('vendedor can view a client profile', function () {
    $user = vendedorUser();
    $client = Client::factory()->create();

    $this->actingAs($user)
        ->get(route('vendedor.clientes.show', ['current_team' => $user->currentTeam, 'client' => $client]))
        ->assertOk();
});

test('vendedor can update a client', function () {
    $user = vendedorUser();
    $client = Client::factory()->create(['razon_social' => 'Nombre Viejo S.A.C.']);

    $this->actingAs($user)
        ->put(route('vendedor.clientes.update', ['current_team' => $user->currentTeam, 'client' => $client]), [
            'tipo_documento' => $client->tipo_documento,
            'numero_documento' => $client->numero_documento,
            'razon_social' => 'Nombre Nuevo S.A.C.',
        ])
        ->assertSessionHasNoErrors();

    expect($client->refresh()->razon_social)->toBe('Nombre Nuevo S.A.C.');
});

test('vendedor can add, update and delete a client site', function () {
    $user = vendedorUser();
    $client = Client::factory()->create();

    $this->actingAs($user)
        ->post(route('vendedor.clientes.sites.store', ['current_team' => $user->currentTeam, 'client' => $client]), [
            'tipo' => 'oficina',
            'nombre' => 'Sede principal',
            'direccion' => 'Av. Test 123',
        ])
        ->assertSessionHasNoErrors();

    $site = ClientSite::where('client_id', $client->id)->firstOrFail();

    $this->actingAs($user)
        ->put(route('vendedor.clientes.sites.update', ['current_team' => $user->currentTeam, 'client' => $client, 'site' => $site]), [
            'tipo' => 'oficina',
            'nombre' => 'Sede principal renombrada',
            'direccion' => 'Av. Test 123',
        ])
        ->assertSessionHasNoErrors();

    expect($site->refresh()->nombre)->toBe('Sede principal renombrada');

    $this->actingAs($user)
        ->delete(route('vendedor.clientes.sites.destroy', ['current_team' => $user->currentTeam, 'client' => $client, 'site' => $site]))
        ->assertSessionHasNoErrors();

    $this->assertDatabaseMissing('client_sites', ['id' => $site->id]);
});

test('vendedor can add, update and delete a vehicle', function () {
    $user = vendedorUser();
    $client = Client::factory()->create();

    $this->actingAs($user)
        ->post(route('vendedor.clientes.vehiculos.store', ['current_team' => $user->currentTeam, 'client' => $client]), [
            'placa' => 'ABC-123',
        ])
        ->assertSessionHasNoErrors();

    $vehicle = Vehicle::where('client_id', $client->id)->firstOrFail();

    $this->actingAs($user)
        ->put(route('vendedor.clientes.vehiculos.update', ['current_team' => $user->currentTeam, 'client' => $client, 'vehicle' => $vehicle]), [
            'placa' => 'ABC-123',
            'marca' => 'Toyota',
        ])
        ->assertSessionHasNoErrors();

    expect($vehicle->refresh()->marca)->toBe('Toyota');

    $this->actingAs($user)
        ->delete(route('vendedor.clientes.vehiculos.destroy', ['current_team' => $user->currentTeam, 'client' => $client, 'vehicle' => $vehicle]))
        ->assertSessionHasNoErrors();

    $this->assertDatabaseMissing('vehicles', ['id' => $vehicle->id]);
});
