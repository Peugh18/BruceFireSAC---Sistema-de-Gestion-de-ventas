<?php

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function createGerenteUserForAccessTest(): User
{
    $user = User::factory()->create();
    $user->assignRole('Gerente');

    return $user;
}

function vendedorUserForGerenteTest(): User
{
    $user = User::factory()->create();
    $user->assignRole('Vendedor');

    return $user;
}

function almacenUserForGerenteTest(): User
{
    $user = User::factory()->create();
    $user->assignRole('Almacen');

    return $user;
}

test('usuario con rol Gerente puede acceder al dashboard de gerente', function () {
    $user = createGerenteUserForAccessTest();

    $this->actingAs($user)
        ->get(route('gerente.dashboard', ['current_team' => $user->currentTeam]))
        ->assertOk();
});

test('usuario con rol Vendedor recibe 403 Forbidden al intentar acceder a rutas de gerente', function () {
    $user = vendedorUserForGerenteTest();

    $this->actingAs($user)
        ->get(route('gerente.dashboard', ['current_team' => $user->currentTeam]))
        ->assertForbidden();
});

test('usuario con rol Almacen recibe 403 Forbidden al intentar acceder a rutas de gerente', function () {
    $user = almacenUserForGerenteTest();

    $this->actingAs($user)
        ->get(route('gerente.dashboard', ['current_team' => $user->currentTeam]))
        ->assertForbidden();
});

test('usuario sin rol asignado recibe 403 Forbidden al intentar acceder a rutas de gerente', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('gerente.dashboard', ['current_team' => $user->currentTeam]))
        ->assertForbidden();
});

test('usuario no autenticado es redirigido al login al intentar acceder a rutas de gerente', function () {
    $user = User::factory()->create();

    $this->get(route('gerente.dashboard', ['current_team' => $user->currentTeam]))
        ->assertRedirect(route('login'));
});
