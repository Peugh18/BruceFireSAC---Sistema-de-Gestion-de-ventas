<?php

use App\Models\Quote;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

test('el filtro enviada agrupa los estados internos de cotizacion', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $vendedor = User::factory()->create();
    $vendedor->assignRole('Vendedor');

    foreach (['emitida', 'enviada', 'aceptada'] as $estado) {
        Quote::factory()->create(['estado' => $estado]);
    }

    $response = $this->actingAs($vendedor)->get(route('vendedor.cotizaciones.index', [
        'current_team' => $vendedor->currentTeam,
        'estado' => 'enviada',
    ]));

    $response->assertOk()->assertInertia(fn ($page) => $page
        ->where('quotes.data', fn ($quotes) => collect($quotes)->pluck('estado')->sort()->values()->all() === ['emitida', 'enviada'])
    );
});
