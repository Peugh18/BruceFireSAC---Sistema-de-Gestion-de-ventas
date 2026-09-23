<?php

use App\Models\Certificate;
use App\Models\Client;
use App\Models\Equipment;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Service;
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

test('alert index estima vencimiento desde el historico de ventas cuando no hay equipo registrado', function () {
    $user = vendedorUser();
    $client = Client::factory()->create(['razon_social' => 'CLIENTE HISTORICO SAC']);
    $extintor = Product::factory()->create(['nombre' => 'EXTINTOR PQS 6KG', 'categoria' => 'extintor']);

    $sale = Sale::factory()->create([
        'client_id' => $client->id,
        'fecha' => now()->subYear()->subDay()->toDateString(),
    ]);
    SaleItem::factory()->create([
        'sale_id' => $sale->id,
        'product_id' => $extintor->id,
        'service_id' => null,
        'cantidad' => 2,
    ]);

    $response = $this->actingAs($user)
        ->get(route('vendedor.alertas.index', ['current_team' => $user->currentTeam]))
        ->assertOk();

    $vencidas = collect($response->viewData('page')['props']['alerts']['vencidas']);
    $row = $vencidas->firstWhere('client_id', $client->id);

    expect($row)->not->toBeNull()
        ->and($row['origen'])->toBe('estimado_historico')
        ->and($row['equipment_id'])->toBeNull()
        ->and($row['cantidad'])->toBe(2);
});

test('alert index no colapsa dos compras distintas del mismo cliente en una sola fecha', function () {
    $user = vendedorUser();
    $client = Client::factory()->create();
    $recarga = Service::factory()->create(['nombre' => 'RECARGA Y MANTENIMIENTO DE EXTINTOR PQS 6KG']);

    $ventaVencidaHaceMucho = Sale::factory()->create([
        'client_id' => $client->id,
        'fecha' => now()->subYears(2)->toDateString(),
    ]);
    SaleItem::factory()->create([
        'sale_id' => $ventaVencidaHaceMucho->id,
        'product_id' => null,
        'service_id' => $recarga->id,
        'cantidad' => 1,
    ]);

    $ventaVencidaReciente = Sale::factory()->create([
        'client_id' => $client->id,
        'fecha' => now()->subYear()->subDays(2)->toDateString(),
    ]);
    SaleItem::factory()->create([
        'sale_id' => $ventaVencidaReciente->id,
        'product_id' => null,
        'service_id' => $recarga->id,
        'cantidad' => 3,
    ]);

    $response = $this->actingAs($user)
        ->get(route('vendedor.alertas.index', ['current_team' => $user->currentTeam]))
        ->assertOk();

    $filasDelCliente = collect($response->viewData('page')['props']['alerts']['vencidas'])
        ->where('client_id', $client->id);

    expect($filasDelCliente)->toHaveCount(2);
});

test('alert index no muestra compras que todavia no llegan a su vencimiento', function () {
    $user = vendedorUser();
    $client = Client::factory()->create();
    $extintor = Product::factory()->create(['categoria' => 'extintor']);

    $sale = Sale::factory()->create([
        'client_id' => $client->id,
        'fecha' => now()->subMonths(3)->toDateString(),
    ]);
    SaleItem::factory()->create([
        'sale_id' => $sale->id,
        'product_id' => $extintor->id,
        'service_id' => null,
    ]);

    $response = $this->actingAs($user)
        ->get(route('vendedor.alertas.index', ['current_team' => $user->currentTeam]))
        ->assertOk();

    $alerts = $response->viewData('page')['props']['alerts'];
    $todas = collect($alerts['vencidas'])
        ->concat($alerts['esta_semana'])
        ->concat($alerts['este_mes']);

    expect($todas->where('client_id', $client->id))->toHaveCount(0);
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
