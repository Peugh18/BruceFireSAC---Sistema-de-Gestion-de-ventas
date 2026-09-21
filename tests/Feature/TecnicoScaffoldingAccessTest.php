<?php

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('guest is redirected to login from tecnico-planta and tecnico-campo dashboards', function () {
    $user = User::factory()->create();

    $this->get(route('tecnico-planta.dashboard', ['current_team' => $user->currentTeam]))
        ->assertRedirect(route('login'));

    $this->get(route('tecnico-campo.dashboard', ['current_team' => $user->currentTeam]))
        ->assertRedirect(route('login'));
});

test('user without tecnico role gets 403', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('tecnico-planta.dashboard', ['current_team' => $user->currentTeam]))
        ->assertForbidden();

    $this->actingAs($user)
        ->get(route('tecnico-campo.dashboard', ['current_team' => $user->currentTeam]))
        ->assertForbidden();
});

test('user with vendedor role cannot access tecnico-planta or tecnico-campo', function () {
    $user = User::factory()->create();
    $user->assignRole('Vendedor');

    $this->actingAs($user)
        ->get(route('tecnico-planta.dashboard', ['current_team' => $user->currentTeam]))
        ->assertForbidden();

    $this->actingAs($user)
        ->get(route('tecnico-campo.dashboard', ['current_team' => $user->currentTeam]))
        ->assertForbidden();
});

test('user with TecnicoPlanta role can access planta dashboard and is forbidden on campo', function () {
    $user = User::factory()->create();
    $user->assignRole('TecnicoPlanta');

    $this->actingAs($user)
        ->get(route('tecnico-planta.dashboard', ['current_team' => $user->currentTeam]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tecnico-planta/dashboard')
            ->has('kpis')
        );

    // Forbidden on campo
    $this->actingAs($user)
        ->get(route('tecnico-campo.dashboard', ['current_team' => $user->currentTeam]))
        ->assertForbidden();
});

test('user with TecnicoCampo role can access campo dashboard and is forbidden on planta', function () {
    $user = User::factory()->create();
    $user->assignRole('TecnicoCampo');

    $this->actingAs($user)
        ->get(route('tecnico-campo.dashboard', ['current_team' => $user->currentTeam]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tecnico-campo/dashboard')
            ->has('kpis')
        );

    // Forbidden on planta
    $this->actingAs($user)
        ->get(route('tecnico-planta.dashboard', ['current_team' => $user->currentTeam]))
        ->assertForbidden();
});

test('roles TecnicoPlanta and TecnicoCampo have correct granular permissions seeded', function () {
    $userPlanta = User::factory()->create();
    $userPlanta->assignRole('TecnicoPlanta');

    expect($userPlanta->can('service_orders.receive'))->toBeTrue()
        ->and($userPlanta->can('service_orders.execute'))->toBeTrue()
        ->and($userPlanta->can('deficiencies.resolve'))->toBeTrue()
        ->and($userPlanta->can('service_orders.close'))->toBeFalse(); // Solo Campo cierra en entrega

    $userCampo = User::factory()->create();
    $userCampo->assignRole('TecnicoCampo');

    expect($userCampo->can('service_orders.receive'))->toBeTrue()
        ->and($userCampo->can('service_orders.execute'))->toBeTrue()
        ->and($userCampo->can('service_orders.close'))->toBeTrue()
        ->and($userCampo->can('deficiencies.resolve'))->toBeFalse(); // Planta resuelve deficiencias
});
