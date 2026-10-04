<?php

use App\Models\Sale;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('a las 10 pm de Lima las ventas de hoy siguen siendo las del dia peruano', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-01 03:00:00', 'UTC'));

    $vendedor = User::factory()->create();
    $vendedor->assignRole('Vendedor');
    Sale::factory()->create(['vendedor_id' => $vendedor->id, 'estado' => 'confirmada', 'fecha' => '2026-09-30', 'total' => 765.20]);

    $props = $this->actingAs($vendedor)
        ->get(route('vendedor.ventas.index', ['current_team' => $vendedor->currentTeam]))
        ->assertOk()
        ->viewData('page')['props'];

    expect($props['hoy'])->toBe('2026-09-30')
        ->and((float) $props['kpis']['total_vendido'])->toBe(765.2)
        ->and($props['sales']['data'])->toHaveCount(1);
});
