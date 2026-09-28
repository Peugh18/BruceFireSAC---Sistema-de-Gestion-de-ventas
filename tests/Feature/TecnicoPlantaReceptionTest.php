<?php

use App\Enums\TeamRole;
use App\Models\Client;
use App\Models\Equipment;
use App\Models\ServiceOrder;
use App\Models\ServiceOrderEvent;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->team = Team::factory()->create();
    $this->tecnicoPlanta = User::factory()->create(['current_team_id' => $this->team->id]);
    $this->team->members()->attach($this->tecnicoPlanta, ['role' => TeamRole::Admin->value]);
    $this->tecnicoPlanta->assignRole('TecnicoPlanta');
});

test('tecnico planta can view reception list with pending orders', function () {
    $client = Client::factory()->create();

    ServiceOrder::factory()->create([
        'client_id' => $client->id,
        'codigo' => 'OS-REC-0001',
        'estado' => 'pendiente_recepcion',
    ]);

    $this->actingAs($this->tecnicoPlanta)
        ->get(route('tecnico-planta.recepciones.index', ['current_team' => $this->team]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tecnico-planta/recepciones/index')
            ->has('orders.data', 1)
            ->where('orders.data.0.codigo', 'OS-REC-0001')
            ->where('counts.pendientes', 1)
        );
});

test('tecnico planta can confirm reception transitioning state and logging event', function () {
    $client = Client::factory()->create();
    $order = ServiceOrder::factory()->create([
        'client_id' => $client->id,
        'codigo' => 'OS-REC-0002',
        'estado' => 'pendiente_recepcion',
    ]);

    $this->actingAs($this->tecnicoPlanta)
        ->post(route('tecnico-planta.recepciones.confirm', [
            'current_team' => $this->team,
            'service_order' => $order,
        ]), [
            'observaciones' => 'Ingreso completo a taller',
            'equipos_recibidos_count' => 2,
            'diferencias' => 'Ninguna novedad',
        ])
        ->assertRedirect();

    $order->refresh();
    expect($order->estado)->toBe('recibido_planta')
        ->and($order->departamento_tecnico)->toBe('planta');

    $event = ServiceOrderEvent::where('service_order_id', $order->id)->first();
    expect($event)->not->toBeNull()
        ->and($event->tipo)->toBe('recibida')
        ->and($event->payload['accion'])->toBe('recepcion_planta')
        ->and($event->payload['equipos_recibidos_count'])->toBe(2);
});

test('alta tecnica rapida caso A links existing equipment by barcode', function () {
    $client = Client::factory()->create();
    $order = ServiceOrder::factory()->create([
        'client_id' => $client->id,
        'codigo' => 'OS-REC-0003',
        'estado' => 'recibido_planta',
    ]);

    $existingEquipment = Equipment::factory()->create([
        'client_id' => $client->id,
        'numero_serie' => 'BF-EQ-000100',
    ]);

    $this->actingAs($this->tecnicoPlanta)
        ->post(route('tecnico-planta.recepciones.equipos.store', [
            'current_team' => $this->team,
            'service_order' => $order,
        ]), [
            'numero_serie' => 'BF-EQ-000100',
            'notas' => 'Cilindro con pintura desgastada',
        ])
        ->assertRedirect();

    expect($order->equipments()->where('equipment.id', $existingEquipment->id)->exists())->toBeTrue();
});

test('alta tecnica rapida caso B creates new equipment with sequence generator and unreadable defaults', function () {
    $client = Client::factory()->create();
    $order = ServiceOrder::factory()->create([
        'client_id' => $client->id,
        'codigo' => 'OS-REC-0004',
        'estado' => 'recibido_planta',
    ]);

    $this->actingAs($this->tecnicoPlanta)
        ->post(route('tecnico-planta.recepciones.equipos.store', [
            'current_team' => $this->team,
            'service_order' => $order,
        ]), [
            'tipo_agente' => 'PQS ABC',
            'capacidad' => '6 kg',
            // Marca, serie y año no enviados -> deben ser "No legible / Pendiente de verificar"
        ])
        ->assertRedirect();

    $newEquipment = $order->equipments()->first();
    expect($newEquipment)->not->toBeNull()
        ->and($newEquipment->numero_serie)->toMatch('/^BF-EQ-\d{6}$/')
        ->and($newEquipment->marca)->toBe('No legible / Pendiente de verificar')
        ->and($newEquipment->serie_fabricante)->toBe('No legible / Pendiente de verificar')
        ->and($newEquipment->anio_fabricacion)->toBe('No legible / Pendiente de verificar');

    expect(ServiceOrderEvent::where('service_order_id', $order->id)->where('tipo', 'otro')->exists())->toBeTrue();
});

test('tecnico planta can print barcode stickers for equipments in order', function () {
    $client = Client::factory()->create();
    $order = ServiceOrder::factory()->create([
        'client_id' => $client->id,
        'codigo' => 'OS-REC-0005',
        'estado' => 'recibido_planta',
    ]);

    $equipment = Equipment::factory()->create([
        'client_id' => $client->id,
        'numero_serie' => 'BF-EQ-999999',
        'tipo_agente' => 'PQS ABC',
        'capacidad' => '6 kg',
    ]);

    $order->equipments()->attach($equipment->id, ['recibido' => true]);

    $response = $this->actingAs($this->tecnicoPlanta)
        ->get(route('tecnico-planta.recepciones.stickers', [
            'current_team' => $this->team,
            'service_order' => $order,
        ]));

    $response->assertOk();
    expect($response->headers->get('content-type'))->toBe('application/pdf');
});
