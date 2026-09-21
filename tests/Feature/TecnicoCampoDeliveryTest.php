<?php

use App\Enums\TeamRole;
use App\Models\Client;
use App\Models\Equipment;
use App\Models\ServiceOrder;
use App\Models\ServiceOrderEvent;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\CertificateTypeSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed([RolesAndPermissionsSeeder::class, CertificateTypeSeeder::class]);
    $this->team = Team::factory()->create();
    $this->user = User::factory()->create(['current_team_id' => $this->team->id]);
    $this->team->members()->attach($this->user, ['role' => TeamRole::Admin->value]);
    $this->user->assignRole('TecnicoCampo');
});

test('tecnico campo can view delivery index with KPIs', function () {
    $client = Client::factory()->create();
    $order = ServiceOrder::factory()->create([
        'client_id' => $client->id,
        'departamento_tecnico' => 'campo',
        'estado' => 'listo_entrega',
    ]);

    $this->actingAs($this->user)
        ->get(route('tecnico-campo.entregas.index', ['current_team' => $this->team]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tecnico-campo/entregas/index')
            ->has('entregas.data', 1)
            ->has('stats')
        );
});

test('tecnico campo can view delivery detail with custody trail', function () {
    $client = Client::factory()->create();
    $order = ServiceOrder::factory()->create([
        'client_id' => $client->id,
        'departamento_tecnico' => 'campo',
        'estado' => 'listo_entrega',
    ]);

    $equipment = Equipment::factory()->create([
        'client_id' => $client->id,
        'numero_serie' => 'EXT-ENTREGA-01',
    ]);
    $order->equipments()->attach($equipment->id);

    // Evento previo de custodia
    ServiceOrderEvent::create([
        'service_order_id' => $order->id,
        'tipo' => 'recibida',
        'user_id' => $this->user->id,
        'payload' => [
            'eslabon_custodia' => 'recojo_campo',
            'responsable_nombre' => $this->user->name,
        ],
    ]);

    $this->actingAs($this->user)
        ->get(route('tecnico-campo.entregas.show', [
            'current_team' => $this->team,
            'service_order' => $order->id,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tecnico-campo/entregas/show')
            ->has('order.equipments', 1)
            ->has('custodyEvents', 1)
        );
});

test('tecnico campo can confirm final delivery and close service order', function () {
    $client = Client::factory()->create();
    $order = ServiceOrder::factory()->create([
        'client_id' => $client->id,
        'departamento_tecnico' => 'campo',
        'estado' => 'listo_entrega',
    ]);

    $this->actingAs($this->user)
        ->post(route('tecnico-campo.entregas.confirm', [
            'current_team' => $this->team,
            'service_order' => $order->id,
        ]), [
            'receptor_nombre' => 'María Del Carmen (Gerente Operaciones)',
            'receptor_dni' => 'DNI 09483210',
            'observaciones_entrega' => 'Extintores ubicados según mapa de evacuación',
            'conformidad_aceptada' => true,
            'cerrar_orden' => true,
        ])
        ->assertRedirect(route('tecnico-campo.entregas.show', [
            'current_team' => $this->team,
            'service_order' => $order->id,
        ]));

    expect($order->fresh()->estado)->toBe('cerrado');

    $event = ServiceOrderEvent::where('service_order_id', $order->id)
        ->where('tipo', 'trabajo_completado')
        ->latest('id')
        ->first();

    expect($event)->not->toBeNull();
    expect($event->payload['eslabon_custodia'])->toBe('entrega_campo');
    expect($event->payload['receptor_nombre'])->toBe('María Del Carmen (Gerente Operaciones)');
});

test('delivery confirmation requires client conformity', function () {
    $client = Client::factory()->create();
    $order = ServiceOrder::factory()->create([
        'client_id' => $client->id,
        'departamento_tecnico' => 'campo',
        'estado' => 'listo_entrega',
    ]);

    $this->actingAs($this->user)
        ->post(route('tecnico-campo.entregas.confirm', [
            'current_team' => $this->team,
            'service_order' => $order->id,
        ]), [
            'receptor_nombre' => 'María Del Carmen',
            'conformidad_aceptada' => false,
        ])
        ->assertSessionHasErrors('conformidad_aceptada');
});

test('tecnico campo can download acta de conformidad PDF', function () {
    $client = Client::factory()->create();
    $order = ServiceOrder::factory()->create([
        'client_id' => $client->id,
        'departamento_tecnico' => 'campo',
        'estado' => 'listo_entrega',
    ]);

    $equipment = Equipment::factory()->create([
        'client_id' => $client->id,
        'numero_serie' => 'EXT-PDF-01',
    ]);
    $order->equipments()->attach($equipment->id);

    $response = $this->actingAs($this->user)
        ->get(route('tecnico-campo.entregas.pdf', [
            'current_team' => $this->team,
            'service_order' => $order->id,
        ]));

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('application/pdf');
});
