<?php

use App\Models\Certificate;
use App\Models\Equipment;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('alert index clasifica equipos vencidos y de esta semana', function () {
    $user = vendedorUser();
    $vencido = Equipment::factory()->create([
        'proxima_fecha_atencion' => now()->subDay()->toDateString(),
        'proxima_prueba_hidrostatica' => null,
    ]);
    $semana = Equipment::factory()->create([
        'proxima_fecha_atencion' => now()->addDays(3)->toDateString(),
        'proxima_prueba_hidrostatica' => null,
    ]);

    $response = $this->actingAs($user)
        ->get(route('vendedor.alertas.index', ['current_team' => $user->currentTeam]))
        ->assertOk();

    $alerts = $response->viewData('page')['props']['alerts'];

    expect(collect($alerts['vencidas'])->pluck('equipment_id')->all())
        ->toContain($vencido->id)
        ->and(collect($alerts['esta_semana'])->pluck('equipment_id')->all())
        ->toContain($semana->id);
});

test('recompute alerts marca vencidos certificados pasados y no toca futuros', function () {
    $expired = Certificate::factory()->create([
        'estado' => 'vigente',
        'fecha_vigencia_hasta' => now()->subDay()->toDateString(),
    ]);
    $future = Certificate::factory()->create([
        'estado' => 'vigente',
        'fecha_vigencia_hasta' => now()->addDay()->toDateString(),
    ]);

    $this->artisan('alerts:recompute')->assertSuccessful();

    expect($expired->refresh()->estado)->toBe('vencido')
        ->and($future->refresh()->estado)->toBe('vigente');
});
