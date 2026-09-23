<?php

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

test('a 403 from role middleware renders the branded error page, not the generic laravel page', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $user = User::factory()->create();
    $user->assignRole('Vendedor');

    $response = $this->actingAs($user)->get(route('almacen.dashboard', [
        'current_team' => $user->currentTeam,
    ]));

    $response->assertForbidden();
    $response->assertInertia(fn ($page) => $page
        ->component('errors/error')
        ->where('status', 403)
    );
});

test('a 404 for a missing route renders the branded error page', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/'.$user->currentTeam->slug.'/ruta-que-no-existe');

    $response->assertNotFound();
    $response->assertInertia(fn ($page) => $page
        ->component('errors/error')
        ->where('status', 404)
    );
});
