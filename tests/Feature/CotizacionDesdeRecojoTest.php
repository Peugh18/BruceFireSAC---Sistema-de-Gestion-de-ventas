<?php

use App\Models\Client;
use App\Models\Equipment;
use App\Models\Quote;
use App\Models\Sede;
use App\Models\Service;
use App\Models\ServiceOrder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->vendedor = vendedorUser();
    $this->sede = Sede::factory()->tienda()->create();
    $this->vendedor->update(['sede_id' => $this->sede->id]);
    $this->orden = ServiceOrder::factory()->create(['sede_id' => $this->sede->id, 'referencia' => 'Mercado · Puesto 24', 'observaciones' => 'Precio acordado con cliente: S/ 60', 'estado' => 'en_proceso']);
    $this->orden->lines()->create(['service_id' => $this->orden->service_id, 'cantidad' => 2]);
    $this->orden->equipments()->attach(Equipment::factory()->create(['client_id' => $this->orden->client_id])->id);
    $this->datos = ['service_order_id' => $this->orden->id, 'client_id' => $this->orden->client_id, 'fecha' => today()->toDateString(), 'vigencia_hasta' => today()->addDays(15)->toDateString(), 'destino' => 'local_cliente', 'referencia' => $this->orden->referencia, 'items' => [['service_id' => $this->orden->service_id, 'cantidad' => 2, 'precio_unitario' => 30]]];
});

test('cotizacion de recojo reutiliza cliente referencia observaciones y servicios', function () {
    $this->actingAs($this->vendedor)->get(route('vendedor.cotizaciones.create', ['current_team' => $this->vendedor->currentTeam, 'service_order_id' => $this->orden->id]))
        ->assertOk()->assertInertia(fn (Assert $page) => $page->where('ordenOrigen.id', $this->orden->id)->where('ordenOrigen.client.id', $this->orden->client_id)->where('ordenOrigen.referencia', 'Mercado · Puesto 24')->where('ordenOrigen.observaciones', 'Precio acordado con cliente: S/ 60')->where('ordenOrigen.items.0.cantidad', 2));
});

test('cotizar vincula equipos y responsable sin duplicar orden ni repetir cotizacion', function () {
    $ruta = route('vendedor.cotizaciones.store', $this->vendedor->currentTeam);
    $this->actingAs($this->vendedor)->post($ruta, $this->datos)->assertRedirect();
    $cotizacion = Quote::findOrFail($this->orden->refresh()->quote_id);
    expect($cotizacion->client_id)->toBe($this->orden->client_id)->and($cotizacion->vendedor_id)->toBe($this->vendedor->id)->and($cotizacion->observaciones)->toBe('Precio acordado con cliente: S/ 60')->and($cotizacion->equipments()->count())->toBe(1)->and(ServiceOrder::count())->toBe(1)->and($this->orden->estado)->toBe('en_proceso');
    $this->post($ruta, $this->datos)->assertSessionHasErrors('service_order_id');
    expect(Quote::count())->toBe(1);
});

test('cotizar un recojo conserva cada servicio y cantidad de su orden', function () {
    $this->actingAs($this->vendedor)
        ->post(route('vendedor.cotizaciones.store', $this->vendedor->currentTeam), [
            ...$this->datos,
            'items' => [[
                'service_id' => $this->orden->service_id,
                'cantidad' => 1,
                'precio_unitario' => 30,
            ]],
        ])
        ->assertSessionHasErrors('items');

    expect(Quote::count())->toBe(0)
        ->and($this->orden->fresh()->quote_id)->toBeNull();
});

test('el vendedor define varios servicios en un recojo general y planta recibe sus casilleros', function () {
    $co2 = Service::factory()->create(['nombre' => 'Recarga CO2 5 lb', 'agente' => 'co2', 'capacidad' => '5 lb']);
    $pqs = Service::factory()->create(['nombre' => 'Recarga y mantenimiento PQS 6 kg', 'agente' => 'pqs', 'capacidad' => '6 kg']);
    $order = ServiceOrder::factory()->create([
        'sede_id' => $this->sede->id,
        'service_id' => null,
        'referencia' => 'Mercado · Puesto 24',
        'estado' => 'recibido_planta',
    ]);
    $order->events()->create([
        'tipo' => 'creada',
        'payload' => ['cantidad_esperada' => 2],
    ]);
    $equipos = Equipment::factory()->sequence(
        ['tipo_agente' => 'CO2', 'capacidad' => '5 lb'],
        ['tipo_agente' => 'PQS ABC', 'capacidad' => '6 kg'],
    )->count(2)->create(['client_id' => $order->client_id]);
    $order->equipments()->attach($equipos->modelKeys(), ['recibido' => true]);

    $this->actingAs($this->vendedor)
        ->get(route('vendedor.cotizaciones.create', ['current_team' => $this->vendedor->currentTeam, 'service_order_id' => $order->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('ordenOrigen.tipo_servicio', 'Recojo de extintores')
            ->has('ordenOrigen.items', 0));

    $datos = [
        'service_order_id' => $order->id,
        'client_id' => $order->client_id,
        'fecha' => today()->toDateString(),
        'vigencia_hasta' => today()->addDays(15)->toDateString(),
        'destino' => 'local_cliente',
        'referencia' => $order->referencia,
        'items' => [
            ['service_id' => $co2->id, 'cantidad' => 1, 'precio_unitario' => 30],
            ['service_id' => $pqs->id, 'cantidad' => 1, 'precio_unitario' => 40],
        ],
    ];

    $this->post(route('vendedor.cotizaciones.store', $this->vendedor->currentTeam), $datos)->assertRedirect();

    $order->refresh()->load('lines');
    $porServicio = $order->lines->keyBy('service_id');

    expect($order->service_id)->toBe($co2->id)
        ->and($order->lines)->toHaveCount(2)
        ->and($order->equipments()->wherePivot('service_order_line_id', $porServicio[$co2->id]->id)->first()->tipo_agente)->toBe('CO2')
        ->and($order->equipments()->wherePivot('service_order_line_id', $porServicio[$pqs->id]->id)->first()->tipo_agente)->toBe('PQS ABC');
});

test('no permite cotizar servicios incompatibles con los extintores registrados', function () {
    $co2 = Service::factory()->create(['nombre' => 'Recarga CO2 5 lb', 'agente' => 'co2', 'capacidad' => '5 lb']);
    $order = ServiceOrder::factory()->create([
        'sede_id' => $this->sede->id,
        'service_id' => null,
        'estado' => 'recibido_planta',
    ]);
    $order->events()->create(['tipo' => 'creada', 'payload' => ['cantidad_esperada' => 1]]);
    $order->equipments()->attach(Equipment::factory()->create([
        'client_id' => $order->client_id,
        'tipo_agente' => 'PQS ABC',
        'capacidad' => '6 kg',
    ])->id, ['recibido' => true]);

    $this->actingAs($this->vendedor)
        ->post(route('vendedor.cotizaciones.store', $this->vendedor->currentTeam), [
            'service_order_id' => $order->id,
            'client_id' => $order->client_id,
            'fecha' => today()->toDateString(),
            'vigencia_hasta' => today()->addDays(15)->toDateString(),
            'destino' => 'local_cliente',
            'referencia' => $order->referencia,
            'items' => [['service_id' => $co2->id, 'cantidad' => 1, 'precio_unitario' => 30]],
        ])
        ->assertSessionHasErrors('items');

    expect(Quote::count())->toBe(0)
        ->and($order->fresh()->quote_id)->toBeNull()
        ->and($order->lines()->count())->toBe(0);
});

test('al corregir la cotizacion pendiente actualiza las lineas que vera Planta', function () {
    $recarga = Service::factory()->create(['nombre' => 'Recarga CO2 5 lb', 'agente' => 'co2', 'capacidad' => '5 lb']);
    $mantenimiento = Service::factory()->create(['nombre' => 'Mantenimiento CO2 5 lb', 'agente' => 'co2', 'capacidad' => '5 lb']);
    $order = ServiceOrder::factory()->create([
        'sede_id' => $this->sede->id,
        'service_id' => null,
        'estado' => 'recibido_planta',
    ]);
    $order->events()->create(['tipo' => 'creada', 'payload' => ['cantidad_esperada' => 1]]);
    $equipment = Equipment::factory()->create(['client_id' => $order->client_id, 'tipo_agente' => 'CO2', 'capacidad' => '5 lb']);
    $order->equipments()->attach($equipment->id, ['recibido' => true]);
    $ruta = route('vendedor.cotizaciones.store', $this->vendedor->currentTeam);
    $cabecera = [
        'service_order_id' => $order->id,
        'client_id' => $order->client_id,
        'fecha' => today()->toDateString(),
        'vigencia_hasta' => today()->addDays(15)->toDateString(),
        'destino' => 'local_cliente',
        'referencia' => $order->referencia,
    ];

    $this->actingAs($this->vendedor)->post($ruta, [...$cabecera, 'items' => [
        ['service_id' => $recarga->id, 'cantidad' => 1, 'precio_unitario' => 30],
    ]])->assertRedirect();

    $quote = Quote::sole();
    $this->put(route('vendedor.cotizaciones.update', ['current_team' => $this->vendedor->currentTeam, 'quote' => $quote]), [...$cabecera, 'items' => [
        ['service_id' => $mantenimiento->id, 'cantidad' => 1, 'precio_unitario' => 35],
    ]])->assertRedirect();

    $order->refresh()->load('lines');
    expect($order->lines)->toHaveCount(1)
        ->and($order->lines->sole()->service_id)->toBe($mantenimiento->id)
        ->and($order->equipments()->first()->pivot->service_order_line_id)->toBe($order->lines->sole()->id);
});

test('no cotiza orden de otra sede ni cambia cliente de la orden', function () {
    $this->actingAs($this->vendedor)->post(route('vendedor.cotizaciones.store', $this->vendedor->currentTeam), [...$this->datos, 'client_id' => Client::factory()->create()->id])->assertSessionHasErrors('client_id');
    $this->orden->update(['sede_id' => Sede::factory()->tienda()->create()->id]);
    $this->post(route('vendedor.cotizaciones.store', $this->vendedor->currentTeam), $this->datos)->assertNotFound();
    expect(Quote::count())->toBe(0);
});
