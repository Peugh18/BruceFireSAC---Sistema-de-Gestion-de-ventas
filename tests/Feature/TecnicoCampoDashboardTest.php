<?php

use App\Enums\TeamRole;
use App\Models\Client;
use App\Models\Service;
use App\Models\ServiceOrder;
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

test('graficos de campo cuentan visitas registradas y trabajos sin duplicar la orden', function () {
    $orden = ServiceOrder::factory()->create(['departamento_tecnico' => 'campo', 'tecnico_id' => $this->tecnicoCampo->id, 'service_id' => null]);
    foreach (['inspeccion_campo_finalizada', 'entrega_final_realizada'] as $accion) {
        $orden->events()->create(['tipo' => 'trabajo_completado', 'user_id' => $this->tecnicoCampo->id, 'payload' => ['accion' => $accion], 'created_at' => now()]);
    }

    $this->actingAs($this->tecnicoCampo)->get(route('tecnico-campo.dashboard', ['current_team' => $this->team]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('graficos.terminadas.6.valor', 1)
            ->where('graficos.visitas.1.valor', 1)
            ->where('graficos.visitas.4.valor', 1));
});

test('campo muestra hoy y pendientes anteriores por urgencia, con historial separado', function () {
    $this->travelTo(now()->setDate(2026, 10, 14)->setTime(9, 0));
    $normal = ServiceOrder::factory()->create(['departamento_tecnico' => 'campo', 'fecha' => '2026-10-14', 'prioridad' => 'normal']);
    $urgente = ServiceOrder::factory()->create(['departamento_tecnico' => 'campo', 'fecha' => '2026-10-13', 'prioridad' => 'urgente']);
    $alta = ServiceOrder::factory()->create(['departamento_tecnico' => 'campo', 'fecha' => '2026-10-14', 'prioridad' => 'alta']);
    ServiceOrder::factory()->create(['departamento_tecnico' => 'campo', 'fecha' => '2026-10-15']);
    $terminada = ServiceOrder::factory()->create(['departamento_tecnico' => 'campo', 'fecha' => '2026-10-13', 'estado' => 'cerrado']);
    $url = route('tecnico-campo.dashboard', ['current_team' => $this->team]);

    $this->actingAs($this->tecnicoCampo)->get($url)->assertInertia(fn (Assert $page) => $page
        ->has('orders.data', 3)->where('orders.data.0.id', $urgente->id)->where('orders.data.1.id', $alta->id)
        ->where('graficos.prioridades.0.valor', 1)->has('graficos.terminadas', 7)
        ->where('orders.data.2.id', $normal->id)->where('filters.fecha', '2026-10-14'));
    $this->get($url.'?tab=finalizados')->assertInertia(fn (Assert $page) => $page->has('orders.data', 1)->where('orders.data.0.id', $terminada->id));
    $this->get($url.'?fecha=2026-10-15')->assertInertia(fn (Assert $page) => $page->has('orders.data', 1)->where('orders.data.0.fecha', '2026-10-15'));
    $this->getJson($url.'?fecha=invalida')->assertUnprocessable()->assertJsonValidationErrors('fecha');
});

test('la entrega asignada de planta aparece en inicio de campo sin cambiar su area', function () {
    $orden = ServiceOrder::factory()->create(['departamento_tecnico' => 'planta', 'estado' => 'listo_entrega', 'tecnico_id' => $this->tecnicoCampo->id, 'fecha' => today()->toDateString()]);
    $this->actingAs($this->tecnicoCampo)->get(route('tecnico-campo.dashboard', ['current_team' => $this->team]))
        ->assertInertia(fn (Assert $page) => $page->has('orders.data', 1)->where('orders.data.0.id', $orden->id)->where('orders.data.0.accion_sugerida', 'entrega'));
    expect($orden->fresh()->departamento_tecnico)->toBe('planta');
});

test('las listas de campo entregan una busqueda vacia como texto y no null', function () {
    foreach (['inspecciones', 'instalaciones', 'entregas', 'mantenimientos'] as $lista) {
        $this->actingAs($this->tecnicoCampo)->get(route("tecnico-campo.{$lista}.index", ['current_team' => $this->team, 'q' => '']))
            ->assertOk()->assertInertia(fn (Assert $page) => $page->where('search', ''));
    }
});

test('tecnico campo dashboard renders kpis and service list', function () {
    $client = Client::factory()->create(['direccion_fiscal' => 'Av. Larco 123, Trujillo']);
    $collectionService = Service::factory()->create(['nombre' => 'Recojo de extintores']);
    $inspectionService = Service::factory()->create(['nombre' => 'Inspección técnica anual']);

    ServiceOrder::factory()->create([
        'client_id' => $client->id,
        'service_id' => $collectionService->id,
        'codigo' => 'OS-CMP-0001',
        'estado' => 'pendiente_recepcion',
        'departamento_tecnico' => 'campo',
    ]);

    ServiceOrder::factory()->create([
        'client_id' => $client->id,
        'service_id' => $inspectionService->id,
        'codigo' => 'OS-CMP-0002',
        'estado' => 'en_proceso',
        'departamento_tecnico' => 'campo',
    ]);

    $this->actingAs($this->tecnicoCampo)
        ->get(route('tecnico-campo.dashboard', ['current_team' => $this->team]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tecnico-campo/dashboard')
            ->has('kpis', fn (Assert $kpis) => $kpis
                ->where('total_servicios', 2)
                ->where('pendientes', 1)
                ->where('en_proceso', 1)
                ->where('finalizados', 0)
            )
            ->has('orders.data', 2)
        );
});

test('tecnico campo dashboard filters by status tab and service type', function () {
    $client = Client::factory()->create();
    $collectionService = Service::factory()->create(['nombre' => 'Recojo']);
    $inspectionService = Service::factory()->create(['nombre' => 'Inspección técnica']);

    ServiceOrder::factory()->create([
        'client_id' => $client->id,
        'service_id' => $collectionService->id,
        'codigo' => 'OS-CMP-1001',
        'estado' => 'pendiente_recepcion',
        'departamento_tecnico' => 'campo',
    ]);

    ServiceOrder::factory()->create([
        'client_id' => $client->id,
        'service_id' => $inspectionService->id,
        'codigo' => 'OS-CMP-1002',
        'estado' => 'en_proceso',
        'departamento_tecnico' => 'campo',
    ]);

    // Query tab pendientes
    $this->actingAs($this->tecnicoCampo)
        ->get(route('tecnico-campo.dashboard', [
            'current_team' => $this->team,
            'tab' => 'pendientes',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('orders.data', 1)
            ->where('orders.data.0.codigo', 'OS-CMP-1001')
        );

    // Query tipo inspecciones
    $this->actingAs($this->tecnicoCampo)
        ->get(route('tecnico-campo.dashboard', [
            'current_team' => $this->team,
            'tipo' => 'inspecciones',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('orders.data', 1)
            ->where('orders.data.0.codigo', 'OS-CMP-1002')
        );
});

test('tecnico campo no ve ordenes anuladas ni de otro tecnico en lista ni kpis', function () {
    $otro = User::factory()->create();
    ServiceOrder::factory()->create(['estado' => 'anulada', 'departamento_tecnico' => 'campo']);
    ServiceOrder::factory()->create(['estado' => 'pendiente_recepcion', 'departamento_tecnico' => 'campo', 'tecnico_id' => $otro->id]);
    ServiceOrder::factory()->create(['estado' => 'pendiente_recepcion', 'departamento_tecnico' => 'campo', 'tecnico_id' => $this->tecnicoCampo->id]);

    $this->actingAs($this->tecnicoCampo)
        ->get(route('tecnico-campo.dashboard', ['current_team' => $this->team]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('kpis.total_servicios', 1)
            ->where('kpis.pendientes', 1)
            ->has('orders.data', 1));
});

test('una inspeccion por recibir se sugiere como inspeccion y no entra en recojos; el total incluye cerradas', function () {
    $inspeccion = Service::factory()->create(['nombre' => 'Inspección técnica']);
    ServiceOrder::factory()->create(['service_id' => $inspeccion->id, 'estado' => 'pendiente_recepcion', 'departamento_tecnico' => 'campo']);
    ServiceOrder::factory()->create(['estado' => 'cerrado', 'departamento_tecnico' => 'campo']);

    $this->actingAs($this->tecnicoCampo)
        ->get(route('tecnico-campo.dashboard', ['current_team' => $this->team]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('kpis.total_servicios', 2)
            ->where('kpis.finalizados', 1)
            ->has('orders.data', 1)
            ->where('orders.data.0.accion_sugerida', 'inspeccion'));

    $this->actingAs($this->tecnicoCampo)
        ->get(route('tecnico-campo.dashboard', ['current_team' => $this->team, 'tipo' => 'recojos']))
        ->assertInertia(fn (Assert $page) => $page->has('orders.data', 0));
});
