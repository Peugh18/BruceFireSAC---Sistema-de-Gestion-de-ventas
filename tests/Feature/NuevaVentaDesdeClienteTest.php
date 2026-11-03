<?php

use App\Models\Client;
use App\Models\Sede;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia as Assert;

// E-039: «Nueva venta» desde la ficha del cliente daba 500 a un vendedor con sede,
// porque filtraba los clientes por una sede que los clientes no tienen.
test('un vendedor con sede abre nueva venta con el cliente ya elegido', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $tienda = Sede::factory()->create(['tipo' => 'mixta']);
    $vendedor = User::factory()->create();
    $vendedor->assignRole('Vendedor');
    $vendedor->forceFill(['sede_id' => $tienda->id])->save();
    $cliente = Client::factory()->create();

    $this->actingAs($vendedor)
        ->get(route('vendedor.ventas.create', ['current_team' => $vendedor->currentTeam, 'client_id' => $cliente->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('cliente.id', $cliente->id));
});
