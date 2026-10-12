<?php

use App\Models\Client;
use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(fn () => $this->seed(RolesAndPermissionsSeeder::class));

test('Nueva cotización desde la ficha del cliente precarga al cliente sin romperse por la sede', function () {
    $vendedor = vendedorUser();
    $cliente = Client::factory()->create();

    $this->actingAs($vendedor)
        ->get(route('vendedor.cotizaciones.create', ['current_team' => $vendedor->currentTeam, 'client_id' => $cliente->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('vendedor/cotizaciones/nueva')
            ->where('cliente.id', $cliente->id)
        );
});
