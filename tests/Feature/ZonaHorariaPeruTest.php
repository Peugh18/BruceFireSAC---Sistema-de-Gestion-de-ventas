<?php

use App\Models\Sale;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('a las 10 pm de Lima las ventas del mes siguen siendo las del mes peruano', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-01 03:00:00', 'UTC'));

    $vendedor = User::factory()->create();
    $vendedor->assignRole('Vendedor');
    Sale::factory()->create(['vendedor_id' => $vendedor->id, 'fecha' => '2026-09-27', 'total' => 765.20]);

    $kpis = $this->actingAs($vendedor)
        ->get(route('vendedor.ventas.index', ['current_team' => $vendedor->currentTeam]))
        ->assertOk()
        ->viewData('page')['props']['kpis'];

    expect((float) $kpis['ventas_del_mes'])->toBe(765.2);
});
