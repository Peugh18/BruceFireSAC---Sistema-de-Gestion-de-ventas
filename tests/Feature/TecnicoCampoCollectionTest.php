<?php

use App\Actions\Equipment\QuickRegisterEquipment;
use App\Enums\TeamRole;
use App\Models\Client;
use App\Models\Equipment;
use App\Models\Evidencia;
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

test('campo puede tomar una inspeccion libre y queda asignado a cargo', function () {
    $service = Service::factory()->create(['nombre' => 'Inspección de operatividad']);
    $order = ServiceOrder::factory()->create([
        'service_id' => $service->id,
        'estado' => 'en_proceso',
        'departamento_tecnico' => 'campo',
        'tecnico_id' => null,
    ]);

    $this->actingAs($this->tecnicoCampo)
        ->get(route('tecnico-campo.inspecciones.show', [
            'current_team' => $this->team,
            'service_order' => $order,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('asignacion.area', 'campo')
            ->where('asignacion.tecnico', null)
            ->where('asignacion.es_mia', false));

    $this->post(route('tecnico-campo.ordenes.tomar', [
        'current_team' => $this->team,
        'service_order' => $order,
    ]))->assertRedirect();

    expect($order->fresh()->tecnico_id)->toBe($this->tecnicoCampo->id)
        ->and($order->events()->where('payload->accion', 'orden_tomada')->count())->toBe(1);
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
    // Recogido, la orden va al taller: pasa a Planta y sin técnico para que
    // el de planta la reciba.
    expect($order->tecnico_id)->toBeNull()
        ->and($order->departamento_tecnico)->toBe('planta')
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
        'service_id' => Service::factory()->create(['nombre' => 'Recarga y mantenimiento PQS 6 kg'])->id,
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

test('recojo espontaneo permite identificar el equipo despues sin inventar datos tecnicos', function () {
    $client = Client::factory()->create();
    $service = Service::factory()->create(['nombre' => 'Recarga PQS 6 kg']);
    $order = ServiceOrder::factory()->create([
        'client_id' => $client->id,
        'service_id' => $service->id,
        'estado' => 'pendiente_recepcion',
        'departamento_tecnico' => 'campo',
    ]);
    $order->lines()->create(['service_id' => $service->id, 'cantidad' => 1]);
    $order->events()->create(['tipo' => 'creada', 'user_id' => $this->tecnicoCampo->id, 'payload' => ['origen' => 'campo']]);

    $this->actingAs($this->tecnicoCampo)
        ->post(route('tecnico-campo.recojos.equipos.store', ['current_team' => $this->team, 'service_order' => $order]), [])
        ->assertRedirect();

    $equipment = $order->equipments()->sole();
    expect($equipment->tipo_agente)->toBe(QuickRegisterEquipment::DEFAULT_UNREADABLE)
        ->and($equipment->capacidad)->toBe(QuickRegisterEquipment::DEFAULT_UNREADABLE)
        ->and($equipment->fecha_venta)->toBeNull()
        ->and($equipment->proxima_fecha_atencion)->toBeNull()
        ->and($equipment->proxima_prueba_hidrostatica)->toBeNull();
});

test('recojo espontaneo exige foto y cantidad exacta antes de pasar a planta', function () {
    $service = Service::factory()->create();
    $order = ServiceOrder::factory()->create(['service_id' => $service->id, 'estado' => 'pendiente_recepcion', 'departamento_tecnico' => 'campo']);
    $order->lines()->create(['service_id' => $service->id, 'cantidad' => 2]);
    $order->events()->create(['tipo' => 'creada', 'user_id' => $this->tecnicoCampo->id, 'payload' => ['origen' => 'campo']]);
    $equipment = Equipment::factory()->create(['client_id' => $order->client_id]);
    $order->equipments()->attach($equipment, ['recibido' => false]);
    $url = route('tecnico-campo.recojos.store', ['current_team' => $this->team, 'service_order' => $order]);
    $datos = ['contacto_nombre' => 'Responsable del puesto', 'conformidad_cliente' => true];

    $this->actingAs($this->tecnicoCampo)->post($url, $datos)->assertRedirect()->assertSessionHasErrors('recojo');
    expect($order->events()->where('payload->eslabon_custodia', 'recojo_campo')->exists())->toBeFalse();

    Evidencia::factory()->create(['service_order_id' => $order->id, 'etapa' => 'recojo']);
    $this->post($url, $datos)->assertRedirect()->assertSessionHasErrors('recojo');
    expect($order->fresh()->estado)->toBe('pendiente_recepcion');
});

test('un recojo sin equipos devuelve un error util sin registrar custodia', function () {
    $orden = ServiceOrder::factory()->create(['departamento_tecnico' => 'campo', 'estado' => 'pendiente_recepcion']);
    $this->actingAs($this->tecnicoCampo)->post(route('tecnico-campo.recojos.store', ['current_team' => $this->team, 'service_order' => $orden]), ['contacto_nombre' => 'Cliente Demo', 'conformidad_cliente' => true])
        ->assertRedirect()->assertSessionHasErrors('recojo');
    expect($orden->events()->count())->toBe(0);
});

test('una firma invalida no deja el recojo registrado a medias', function () {
    $orden = ServiceOrder::factory()->create(['departamento_tecnico' => 'campo', 'estado' => 'pendiente_recepcion']);
    $equipo = Equipment::factory()->create(['client_id' => $orden->client_id]);
    $orden->equipments()->attach($equipo);
    $this->actingAs($this->tecnicoCampo)->post(route('tecnico-campo.recojos.store', ['current_team' => $this->team, 'service_order' => $orden]), ['contacto_nombre' => 'Cliente Demo', 'conformidad_cliente' => true, 'firma' => 'data:image/png;base64,AAAA'])
        ->assertRedirect()->assertSessionHasErrors('firma');
    expect($orden->events()->count())->toBe(0)->and($orden->fresh()->departamento_tecnico)->toBe('campo');
});
