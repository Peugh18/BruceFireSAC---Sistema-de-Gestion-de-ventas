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
