<?php

use App\Enums\TeamRole;
use App\Models\Client;
use App\Models\Equipment;
use App\Models\Service;
use App\Models\ServiceOrder;
use App\Models\ServiceOrderEvent;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->team = Team::factory()->create();
    $this->tecnicoCampo = User::factory()->create(['current_team_id' => $this->team->id]);
    $this->team->members()->attach($this->tecnicoCampo, ['role' => TeamRole::Admin->value]);
    $this->tecnicoCampo->assignRole('TecnicoCampo');
});

test('tecnico campo can view recojos list and detail with custody chain', function () {
    $client = Client::factory()->create([
        'direccion_fiscal' => 'Jr. Pizarro 456, Trujillo',
    ]);
    $service = Service::factory()->create(['nombre' => 'Recojo de extintores']);
    $order = ServiceOrder::factory()->create([
        'client_id' => $client->id,
        'service_id' => $service->id,
        'codigo' => 'OS-RCJ-0001',
        'estado' => 'pendiente_recepcion',
        'departamento_tecnico' => 'campo',
    ]);

    $this->actingAs($this->tecnicoCampo)
        ->get(route('tecnico-campo.recojos.index', ['current_team' => $this->team]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tecnico-campo/recojos/index')
            ->has('recojos.data', 1)
            ->where('recojos.data.0.codigo', 'OS-RCJ-0001')
        );

    $this->actingAs($this->tecnicoCampo)
        ->get(route('tecnico-campo.recojos.show', [
            'current_team' => $this->team,
            'service_order' => $order,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tecnico-campo/recojos/show')
            ->where('order.codigo', 'OS-RCJ-0001')
            ->has('custodyEvents')
        );
});

test('registering collection appends custody chain event to ServiceOrderEvent', function () {
    $client = Client::factory()->create();
    $order = ServiceOrder::factory()->create([
        'client_id' => $client->id,
        'codigo' => 'OS-RCJ-0002',
        'estado' => 'pendiente_recepcion',
        'departamento_tecnico' => 'campo',
    ]);
    $equipment = Equipment::factory()->create(['client_id' => $client->id]);
    $order->equipments()->attach($equipment, ['recibido' => false]);

    $this->actingAs($this->tecnicoCampo)
        ->post(route('tecnico-campo.recojos.store', [
            'current_team' => $this->team,
            'service_order' => $order,
        ]), [
            'contacto_nombre' => 'Carlos Mendoza (Jefe de Almacén)',
            'contacto_telefono' => '944112233',
            'observaciones' => '3 extintores PQS 6kg descargados para mantenimiento',
            'conformidad_cliente' => true,
        ])
        ->assertRedirect();

    $order->refresh();
    expect($order->tecnico_id)->toBe($this->tecnicoCampo->id)
        ->and($order->observaciones)->toContain('3 extintores PQS');

    // Verificar eslabón inmutable de Cadena de Custodia (§22.4, §85.6.3)
    $event = ServiceOrderEvent::where('service_order_id', $order->id)
        ->where('payload->eslabon_custodia', 'recojo_campo')
        ->first();

    expect($event)->not->toBeNull()
        ->and($event->payload['responsable_nombre'])->toBe($this->tecnicoCampo->name)
        ->and($event->payload['contacto_cliente'])->toBe('Carlos Mendoza (Jefe de Almacén)')
        ->and($event->payload['cantidad_equipos'])->toBe(1)
        ->and($event->payload['conformidad_cliente'])->toBeTrue();
});

test('tecnico registra cada extintor y descarga la misma constancia de recepcion', function () {
    $client = Client::factory()->create();
    $order = ServiceOrder::factory()->create([
        'client_id' => $client->id,
        'estado' => 'pendiente_recepcion',
        'departamento_tecnico' => 'campo',
    ]);

    $this->actingAs($this->tecnicoCampo)
        ->post(route('tecnico-campo.recojos.equipos.store', [
            'current_team' => $this->team,
            'service_order' => $order,
        ]), ['tipo_agente' => 'PQS ABC', 'capacidad' => '6 kg'])
        ->assertRedirect();

    expect($order->equipments()->count())->toBe(1);

    $this->actingAs($this->tecnicoCampo)
        ->get(route('tecnico-campo.recojos.constancia-recepcion', [
            'current_team' => $this->team,
            'service_order' => $order,
        ]))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});
