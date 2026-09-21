<?php

use App\Enums\TeamRole;
use App\Models\Certificate;
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

test('tecnico campo can view installations index with KPIs', function () {
    $client = Client::factory()->create();
    $order = ServiceOrder::factory()->create([
        'client_id' => $client->id,
        'departamento_tecnico' => 'campo',
        'tipo_servicio' => 'instalacion',
        'estado' => 'en_revision',
    ]);

    $this->actingAs($this->user)
        ->get(route('tecnico-campo.instalaciones.index', ['current_team' => $this->team]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tecnico-campo/instalaciones/index')
            ->has('instalaciones.data', 1)
            ->has('stats')
        );
});

test('tecnico campo can view installation detail', function () {
    $client = Client::factory()->create();
    $order = ServiceOrder::factory()->create([
        'client_id' => $client->id,
        'departamento_tecnico' => 'campo',
        'tipo_servicio' => 'instalacion',
        'estado' => 'en_revision',
    ]);

    $this->actingAs($this->user)
        ->get(route('tecnico-campo.instalaciones.show', [
            'current_team' => $this->team,
            'service_order' => $order->id,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tecnico-campo/instalaciones/show')
            ->has('order')
            ->has('certificateTypes')
        );
});

test('tecnico campo can register installation, creating client equipments and certificate', function () {
    $client = Client::factory()->create();
    $order = ServiceOrder::factory()->create([
        'client_id' => $client->id,
        'departamento_tecnico' => 'campo',
        'tipo_servicio' => 'instalacion',
        'estado' => 'en_revision',
    ]);

    $this->actingAs($this->user)
        ->post(route('tecnico-campo.instalaciones.store', [
            'current_team' => $this->team,
            'service_order' => $order->id,
        ]), [
            'area' => 'Almacén 2do Piso',
            'ubicacion_instalada' => 'Pilar central junto a montacargas',
            'pruebas' => 'Soporte y gancho anclados según norma técnica',
            'conformidad_nombre' => 'Roberto Sánchez (Jefe de Planta)',
            'conformidad_aceptada' => true,
            'emitir_certificado' => true,
            'tipo_certificado_codigo' => 'operatividad_garantia',
            'equipos' => [
                [
                    'tipo_agente' => 'CO2',
                    'capacidad' => '5 lbs',
                    'marca' => 'Bruce Fire',
                    'ubicacion_actual' => 'Pilar central junto a montacargas',
                ],
                [
                    'tipo_agente' => 'PQS',
                    'capacidad' => '6 kg',
                    'marca' => 'Bruce Fire',
                    'ubicacion_actual' => 'Pilar este',
                ],
            ],
        ])
        ->assertRedirect(route('tecnico-campo.instalaciones.show', [
            'current_team' => $this->team,
            'service_order' => $order->id,
        ]));

    // 1. Equipos creados para el cliente (§25)
    expect(Equipment::where('client_id', $client->id)->count())->toBe(2);
    expect($order->equipments()->count())->toBe(2);

    // 2. Orden actualizada
    expect($order->fresh()->estado)->toBe('listo_entrega');

    // 3. Bitácora y cadena de custodia (§22.4, §85.6.3)
    $event = ServiceOrderEvent::where('service_order_id', $order->id)
        ->where('tipo', 'trabajo_completado')
        ->first();

    expect($event)->not->toBeNull();
    expect($event->payload['eslabon_custodia'])->toBe('instalacion_campo');
    expect($event->payload['conformidad_nombre'])->toBe('Roberto Sánchez (Jefe de Planta)');

    // 4. Certificado emitido (§25)
    $cert = Certificate::where('service_order_id', $order->id)->first();
    expect($cert)->not->toBeNull();
});

test('installation requires customer conformity acceptance', function () {
    $client = Client::factory()->create();
    $order = ServiceOrder::factory()->create([
        'client_id' => $client->id,
        'departamento_tecnico' => 'campo',
        'tipo_servicio' => 'instalacion',
        'estado' => 'en_revision',
    ]);

    $this->actingAs($this->user)
        ->post(route('tecnico-campo.instalaciones.store', [
            'current_team' => $this->team,
            'service_order' => $order->id,
        ]), [
            'area' => 'Almacén 2do Piso',
            'ubicacion_instalada' => 'Pilar central',
            'conformidad_nombre' => 'Roberto Sánchez',
            'conformidad_aceptada' => false,
            'equipos' => [
                [
                    'tipo_agente' => 'PQS',
                    'capacidad' => '6 kg',
                ],
            ],
        ])
        ->assertSessionHasErrors('conformidad_aceptada');
});
