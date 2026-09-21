<?php

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function almacenUser(): User
{
    $user = User::factory()->create();
    $user->assignRole('Almacen');

    return $user;
}

function vendedorUserForAlmacenTest(): User
{
    $user = User::factory()->create();
    $user->assignRole('Vendedor');

    return $user;
}

test('usuario con rol Almacen puede acceder al dashboard de almacen', function () {
    $user = almacenUser();

    $this->actingAs($user)
        ->get(route('almacen.dashboard', ['current_team' => $user->currentTeam]))
        ->assertOk();
});

test('usuario con rol Vendedor recibe 403 Forbidden al intentar acceder a rutas de almacen', function () {
    $user = vendedorUserForAlmacenTest();

    $this->actingAs($user)
        ->get(route('almacen.dashboard', ['current_team' => $user->currentTeam]))
        ->assertForbidden();
});

test('usuario sin rol asignado recibe 403 Forbidden al intentar acceder a rutas de almacen', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('almacen.dashboard', ['current_team' => $user->currentTeam]))
        ->assertForbidden();
});

test('usuario no autenticado es redirigido al login al intentar acceder a rutas de almacen', function () {
    $user = User::factory()->create();

    $this->get(route('almacen.dashboard', ['current_team' => $user->currentTeam]))
        ->assertRedirect(route('login'));
});
