<?php

use App\Enums\TeamRole;
use App\Models\Client;
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

    ServiceOrder::factory()->create([
        'client_id' => $client->id,
        'codigo' => 'OS-CMP-0001',
        'tipo_servicio' => 'Recojo de extintores',
        'estado' => 'pendiente_recepcion',
        'departamento_tecnico' => 'campo',
    ]);

    ServiceOrder::factory()->create([
        'client_id' => $client->id,
        'codigo' => 'OS-CMP-0002',
        'tipo_servicio' => 'Inspección técnica anual',
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

    ServiceOrder::factory()->create([
        'client_id' => $client->id,
        'codigo' => 'OS-CMP-1001',
        'tipo_servicio' => 'Recojo',
        'estado' => 'pendiente_recepcion',
        'departamento_tecnico' => 'campo',
    ]);

    ServiceOrder::factory()->create([
        'client_id' => $client->id,
        'codigo' => 'OS-CMP-1002',
        'tipo_servicio' => 'Inspección técnica',
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
