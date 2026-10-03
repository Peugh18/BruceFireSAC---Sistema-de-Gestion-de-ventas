<?php

use App\Actions\Equipment\RenewEquipmentAttentionDate;
use App\Models\Certificate;
use App\Models\Client;
use App\Models\Equipment;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Service;
use App\Services\Ml\CargaHistorico;
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

test('Por vencer estima la recarga de un cliente registrado con su compra mas reciente del sistema anterior', function () {
    $user = vendedorUser();
    $registrado = Client::factory()->create(['numero_documento' => '20111111111', 'razon_social' => 'ANTIGUO REGISTRADO SAC']);
    $conExtintores = Client::factory()->create(['numero_documento' => '20222222222']);
    Equipment::factory()->create(['client_id' => $conExtintores->id]);
    $haceUnAnio = now()->subYear()->subDays(3)->toDateString();
    $linea = fn (string $fecha, string $comprobante, string $documento, string $producto, string $categoria, int $cantidad) => [
        'fecha' => $fecha, 'tipo_doc' => 'F', 'comprobante' => $comprobante, 'documento_cliente' => $documento, 'nombre_cliente' => 'X',
        'categoria' => $categoria, 'producto_original' => $producto, 'cantidad' => $cantidad, 'total' => 100, 'archivo_origen' => 'a',
    ];
    app(CargaHistorico::class)->reemplazar([
        $linea(now()->subYears(2)->toDateString(), 'F001-1', '20111111111', 'EXTINTOR PQS 6KG', 'extintor', 5),
        $linea($haceUnAnio, 'F001-2', '20111111111', 'RECARGA Y MANTENIMIENTO PQS 6KG', 'recarga_mantenimiento', 3),
        $linea($haceUnAnio, 'F001-2', '20111111111', 'CONO DE PVC', 'seguridad', 10),
        $linea($haceUnAnio, 'F001-3', '20222222222', 'RECARGA Y MANTENIMIENTO PQS 6KG', 'recarga_mantenimiento', 1),
        $linea($haceUnAnio, 'F001-4', '20333333333', 'RECARGA Y MANTENIMIENTO PQS 6KG', 'recarga_mantenimiento', 1),
    ]);

    $response = $this->actingAs($user)
        ->get(route('vendedor.alertas.index', ['current_team' => $user->currentTeam]))
        ->assertOk();

    $estimadas = collect($response->viewData('page')['props']['alerts']['vencidas'])->where('origen', 'estimado_historico');

    expect($estimadas)->toHaveCount(1)
        ->and($estimadas->first()['client_id'])->toBe($registrado->id)
        ->and($estimadas->first()['cantidad'])->toBe(3)
        ->and($estimadas->first()['fecha'])->toBe(now()->subDays(3)->toDateString());

    // Si vuelve a comprar en el sistema nuevo, manda su compra nueva.
    Sale::factory()->create(['client_id' => $registrado->id, 'fecha' => now()->subDay()->toDateString(), 'estado' => 'confirmada']);

    $response = $this->actingAs($user)->get(route('vendedor.alertas.index', ['current_team' => $user->currentTeam]));
    expect(collect($response->viewData('page')['props']['alerts']['vencidas'])->where('client_id', $registrado->id))->toBeEmpty();
});

test('extintor descargado o usado entra de inmediato en vencidas por regla de un solo uso', function () {
    $user = vendedorUser();
    $equipment = Equipment::factory()->create([
        'estado' => 'descargado',
        // Fechas a futuro: aun asi debe vencer de inmediato por ser de un solo uso
        'proxima_fecha_atencion' => now()->addMonths(8)->toDateString(),
        'proxima_prueba_hidrostatica' => now()->addYears(3)->toDateString(),
    ]);

    $response = $this->actingAs($user)
        ->get(route('vendedor.alertas.index', ['current_team' => $user->currentTeam]))
        ->assertOk();

    $vencidas = collect($response->viewData('page')['props']['alerts']['vencidas']);
    $item = $vencidas->firstWhere('equipment_id', $equipment->id);

    expect($item)->not->toBeNull()
        ->and($item['tipo_alerta'])->toBe('descargado_uso')
        ->and($item['segmento'])->toBe('vencidas');
});

test('extintor con prueba hidrostatica por vencer se clasifica con ciclo de 5 anios', function () {
    $user = vendedorUser();
    $equipment = Equipment::factory()->create([
        'estado' => 'activo',
        'proxima_fecha_atencion' => now()->addMonths(6)->toDateString(),
        'proxima_prueba_hidrostatica' => now()->addDays(4)->toDateString(),
    ]);

    $response = $this->actingAs($user)
        ->get(route('vendedor.alertas.index', ['current_team' => $user->currentTeam]))
        ->assertOk();

    $estaSemana = collect($response->viewData('page')['props']['alerts']['esta_semana']);
    $item = $estaSemana->firstWhere('equipment_id', $equipment->id);

    expect($item)->not->toBeNull()
        ->and($item['tipo_alerta'])->toBe('prueba_hidrostatica')
        ->and($item['fecha'])->toBe($equipment->proxima_prueba_hidrostatica->toDateString());
});

test('vendedor puede reportar un extintor como descargado o usado', function () {
    $user = vendedorUser();
    $equipment = Equipment::factory()->create([
        'estado' => 'activo',
    ]);

    $response = $this->actingAs($user)
        ->post(route('vendedor.clientes.extintores.reportar-uso', [
            'current_team' => $user->currentTeam,
            'client' => $equipment->client_id,
            'equipment' => $equipment->id,
        ]));

    $response->assertRedirect();
    expect($equipment->refresh()->estado)->toBe('descargado');
});

test('atencion o cierre renueva recarga a 1 anio y ph a 5 anios si se realizo prueba hidrostatica', function () {
    $equipment = Equipment::factory()->create([
        'estado' => 'descargado',
        'proxima_fecha_atencion' => now()->subDay()->toDateString(),
        'proxima_prueba_hidrostatica' => now()->subDay()->toDateString(),
    ]);

    app(RenewEquipmentAttentionDate::class)->execute(collect([$equipment]), true);

    $equipment->refresh();
    expect($equipment->estado)->toBe('activo')
        ->and($equipment->proxima_fecha_atencion->toDateString())->toBe(now()->addYear()->toDateString())
        ->and($equipment->proxima_prueba_hidrostatica->toDateString())->toBe(now()->addYears(5)->toDateString());
});
