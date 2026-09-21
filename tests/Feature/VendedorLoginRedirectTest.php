<?php

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

test('a vendedor user is redirected to their own dashboard after login', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $user = User::factory()->create();
    $user->assignRole('Vendedor');

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('vendedor.dashboard', [
        'current_team' => $user->currentTeam->slug,
    ]));
});

test('a user without the vendedor role still lands on the generic dashboard', function () {
    $user = User::factory()->create();

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', [
        'current_team' => $user->currentTeam->slug,
    ]));
});

test('visiting the generic dashboard bounces a vendedor user to their own dashboard', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $user = User::factory()->create();
    $user->assignRole('Vendedor');

    $response = $this->actingAs($user)->get(route('dashboard', [
        'current_team' => $user->currentTeam->slug,
    ]));

    $response->assertRedirect(route('vendedor.dashboard', [
        'current_team' => $user->currentTeam->slug,
    ]));
});
