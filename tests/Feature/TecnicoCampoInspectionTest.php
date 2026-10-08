<?php

use App\Enums\TeamRole;
use App\Models\Client;
use App\Models\Equipment;
use App\Models\Service;
use App\Models\ServiceOrder;
use App\Models\ServiceOrderEvent;
use App\Models\Team;
use App\Models\TechnicalChecklist;
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

test('tecnico campo can view inspections index with KPIs and tabs', function () {
    $client = Client::factory()->create();
    $service = Service::factory()->create(['nombre' => 'Inspección']);
    $order = ServiceOrder::factory()->create([
        'client_id' => $client->id,
        'service_id' => $service->id,
        'departamento_tecnico' => 'campo',
        'estado' => 'recibido_planta',
    ]);

    $this->actingAs($this->user)
        ->get(route('tecnico-campo.inspecciones.index', ['current_team' => $this->team]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tecnico-campo/inspecciones/index')
            ->has('inspecciones.data', 1)
            ->has('stats')
        );
});

test('tecnico campo can view inspection detail with touch equipment cards', function () {
    $client = Client::factory()->create();
    $service = Service::factory()->create(['nombre' => 'Inspección']);
    $order = ServiceOrder::factory()->create([
        'client_id' => $client->id,
        'service_id' => $service->id,
        'departamento_tecnico' => 'campo',
        'estado' => 'recibido_planta',
    ]);

    $equipment = Equipment::factory()->create([
        'client_id' => $client->id,
        'numero_serie' => 'EXT-001',
    ]);
    $order->equipments()->attach($equipment->id);

    $this->actingAs($this->user)
        ->get(route('tecnico-campo.inspecciones.show', [
            'current_team' => $this->team,
            'service_order' => $order->id,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tecnico-campo/inspecciones/show')
            ->has('order.equipments', 1)
            ->has('elementosChecklist')
        );
});

test('tecnico campo can register new equipment on site and submit digital checklist', function () {
    $client = Client::factory()->create();
    $order = ServiceOrder::factory()->create([
        'client_id' => $client->id,
        'departamento_tecnico' => 'campo',
        'estado' => 'recibido_planta',
    ]);

    // 1. Agregar extintor en sitio
    $this->actingAs($this->user)
        ->post(route('tecnico-campo.inspecciones.equipos.store', [
            'current_team' => $this->team,
            'service_order' => $order->id,
        ]), [
            'tipo_agente' => 'PQS ABC',
            'capacidad' => '6 kg',
            'ubicacion_actual' => 'Recepción principal',
        ])
        ->assertRedirect();

    $equipment = $order->equipments()->first();
    expect($equipment)->not->toBeNull();
    expect($equipment->ubicacion_actual)->toBe('Recepción principal');

    // 2. Ejecutar checklist observando un elemento
    $this->actingAs($this->user)
        ->post(route('tecnico-campo.inspecciones.checklist.store', [
            'current_team' => $this->team,
            'service_order' => $order->id,
            'equipment' => $equipment->id,
        ]), [
            'items' => [
                'cilindro' => [
                    'estado' => 'observado',
                    'condicion' => 'Corrosión visible en base',
                    'requiere_autorizacion' => true,
                ],
                'manometro' => [
                    'estado' => 'conforme',
                ],
            ],
            'observaciones' => 'Requiere mantenimiento correctivo',
        ])
        ->assertRedirect();

    expect(TechnicalChecklist::where('equipment_id', $equipment->id)->exists())->toBeTrue();
    expect($order->deficiencies()->count())->toBe(1);
    expect($order->fresh()->estado)->toBe('esperando_autorizacion');
});

test('el checklist de inspeccion valida items en vez de fallar con error 500 si no llegan', function () {
    $client = Client::factory()->create();
    $order = ServiceOrder::factory()->create([
        'client_id' => $client->id,
        'departamento_tecnico' => 'campo',
        'estado' => 'recibido_planta',
    ]);
    $equipment = Equipment::factory()->create(['client_id' => $client->id]);
    $order->equipments()->attach($equipment->id);

    // Un request sin 'items' debe volver con errores de validación (required),
    // nunca con un 500 por leer una clave inexistente.
    $this->actingAs($this->user)
        ->post(route('tecnico-campo.inspecciones.checklist.store', [
            'current_team' => $this->team,
            'service_order' => $order->id,
            'equipment' => $equipment->id,
        ]), [
            'observaciones' => 'Request sin items',
        ])
        ->assertSessionHasErrors('items');

    expect(TechnicalChecklist::where('equipment_id', $equipment->id)->exists())->toBeFalse();
});

test('tecnico campo can finalize inspection with conformity and log custody chain', function () {
    $client = Client::factory()->create();
    $order = ServiceOrder::factory()->create([
        'client_id' => $client->id,
        'departamento_tecnico' => 'campo',
        'estado' => 'recibido_planta',
    ]);

    $this->actingAs($this->user)
        ->post(route('tecnico-campo.inspecciones.complete', [
            'current_team' => $this->team,
            'service_order' => $order->id,
        ]), [
            'responsable' => 'Carlos Técnico',
            'cargo' => 'Inspector de Seguridad',
            'conformidad_nombre' => 'Juan Perez (Administrador Sede)',
            'conformidad_aceptada' => true,
            'observaciones_generales' => 'Todos los extintores verificados en piso 1 y 2.',
        ])
        ->assertRedirect(route('tecnico-campo.inspecciones.show', [
            'current_team' => $this->team,
            'service_order' => $order->id,
        ]));

    expect($order->fresh()->estado)->toBe('listo_entrega');

    $event = ServiceOrderEvent::where('service_order_id', $order->id)
        ->where('tipo', 'trabajo_completado')
        ->first();

    expect($event)->not->toBeNull();
    expect($event->payload['conformidad_nombre'])->toBe('Juan Perez (Administrador Sede)');
    expect($event->payload['eslabon_custodia'])->toBe('inspeccion_campo');
});
