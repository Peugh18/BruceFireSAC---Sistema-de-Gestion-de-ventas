<?php

use App\Enums\TeamRole;
use App\Models\Client;
use App\Models\Deficiency;
use App\Models\Equipment;
use App\Models\ServiceOrder;
use App\Models\ServiceOrderEvent;
use App\Models\Team;
use App\Models\TechnicalChecklist;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->team = Team::factory()->create();
    $this->tecnicoPlanta = User::factory()->create(['current_team_id' => $this->team->id]);
    $this->team->members()->attach($this->tecnicoPlanta, ['role' => TeamRole::Admin->value]);
    $this->tecnicoPlanta->assignRole('TecnicoPlanta');
});

test('tecnico planta can view checklist form with tailored elements', function () {
    $client = Client::factory()->create();
    $order = ServiceOrder::factory()->create([
        'client_id' => $client->id,
        'codigo' => 'OS-CHK-0001',
        'estado' => 'recibido_planta',
    ]);
    $equipment = Equipment::factory()->create([
        'client_id' => $client->id,
        'numero_serie' => 'BF-EQ-000200',
        'tipo_agente' => 'CO2',
    ]);
    $order->equipments()->attach($equipment->id);

    $this->actingAs($this->tecnicoPlanta)
        ->get(route('tecnico-planta.checklist.create', [
            'current_team' => $this->team,
            'service_order' => $order,
            'equipment' => $equipment,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tecnico-planta/checklist/create')
            ->where('equipment.numero_serie', 'BF-EQ-000200')
            ->has('elementos')
        );
});

test('checklist all conforme advances order to en_proceso', function () {
    $client = Client::factory()->create();
    $order = ServiceOrder::factory()->create([
        'client_id' => $client->id,
        'codigo' => 'OS-CHK-0002',
        'estado' => 'recibido_planta',
    ]);
    $equipment = Equipment::factory()->create([
        'client_id' => $client->id,
        'numero_serie' => 'BF-EQ-000201',
        'tipo_agente' => 'PQS ABC',
    ]);
    $order->equipments()->attach($equipment->id);

    $items = [
        'cilindro' => ['estado' => 'conforme'],
        'valvula' => ['estado' => 'conforme'],
        'manometro' => ['estado' => 'conforme'],
    ];

    $this->actingAs($this->tecnicoPlanta)
        ->post(route('tecnico-planta.checklist.store', [
            'current_team' => $this->team,
            'service_order' => $order,
            'equipment' => $equipment,
        ]), [
            'items' => $items,
            'observaciones' => 'Todo en óptimas condiciones',
        ])
        ->assertRedirect(route('tecnico-planta.recepciones.show', [
            'current_team' => $this->team,
            'service_order' => $order,
        ]));

    $order->refresh();
    expect($order->estado)->toBe('en_proceso');

    $checklist = TechnicalChecklist::where('equipment_id', $equipment->id)->first();
    expect($checklist)->not->toBeNull()
        ->and($checklist->resultado_general)->toBe('conforme');
});

test('checklist with observed element creates deficiency and advances order to esperando_autorizacion', function () {
    $client = Client::factory()->create();
    $order = ServiceOrder::factory()->create([
        'client_id' => $client->id,
        'codigo' => 'OS-CHK-0003',
        'estado' => 'recibido_planta',
    ]);
    $equipment = Equipment::factory()->create([
        'client_id' => $client->id,
        'numero_serie' => 'BF-EQ-000202',
        'tipo_agente' => 'PQS ABC',
    ]);
    $order->equipments()->attach($equipment->id);

    $items = [
        'cilindro' => ['estado' => 'conforme'],
        'manometro' => [
            'estado' => 'observado',
            'condicion' => 'Aguja trabada en cero',
            'accion_recomendada' => 'Cambio de manómetro 1/8',
            'repuesto_sugerido' => 'Manómetro 1/8 195 PSI',
            'requiere_autorizacion' => true,
            'nota' => 'Extintor sin presión aparente',
            'foto' => UploadedFile::fake()->image('foto.jpg'),
        ],
    ];

    $this->actingAs($this->tecnicoPlanta)
        ->post(route('tecnico-planta.checklist.store', [
            'current_team' => $this->team,
            'service_order' => $order,
            'equipment' => $equipment,
        ]), [
            'items' => $items,
        ])
        ->assertRedirect();

    $order->refresh();
    expect($order->estado)->toBe('esperando_autorizacion');

    $deficiency = Deficiency::where('service_order_id', $order->id)->first();
    expect($deficiency)->not->toBeNull()
        ->and($deficiency->componente)->toBe('Manómetro de Presión')
        ->and($deficiency->requiere_autorizacion)->toBeTrue()
        ->and($deficiency->estado)->toBe('esperando_autorizacion');

    // Eventos inmutables de auditoría
    expect(ServiceOrderEvent::where('service_order_id', $order->id)->where('tipo', 'deficiencia_detectada')->exists())->toBeTrue()
        ->and(ServiceOrderEvent::where('service_order_id', $order->id)->where('tipo', 'notificacion_vendedor')->exists())->toBeTrue();
});
