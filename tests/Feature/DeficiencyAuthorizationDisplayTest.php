<?php

use App\Models\Deficiency;
use App\Models\DeficiencyAuthorization;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('deficiencies index formats the authorization date instead of the raw timestamp', function () {
    $user = vendedorUser();
    $deficiency = Deficiency::factory()->create(['estado' => 'autorizada']);
    DeficiencyAuthorization::factory()->create([
        'deficiency_id' => $deficiency->id,
        'fecha' => '2026-09-21',
    ]);

    $response = $this->actingAs($user)->get(route('vendedor.deficiencias.index', [
        'current_team' => $user->currentTeam,
    ]));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('deficiencies.data.0.authorization.fecha', '21/09/2026')
    );
});
