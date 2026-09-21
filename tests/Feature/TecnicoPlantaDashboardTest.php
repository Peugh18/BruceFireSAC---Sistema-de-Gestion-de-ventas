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
    $this->tecnicoPlanta = User::factory()->create(['current_team_id' => $this->team->id]);
    $this->team->members()->attach($this->tecnicoPlanta, ['role' => TeamRole::Admin->value]);
    $this->tecnicoPlanta->assignRole('TecnicoPlanta');
});

test('tecnico planta dashboard renders kpis and default queue', function () {
    $client = Client::factory()->create();

    ServiceOrder::factory()->create([
        'client_id' => $client->id,
        'estado' => 'pendiente_recepcion',
        'codigo' => 'OS-2026-0001',
    ]);

    ServiceOrder::factory()->create([
        'client_id' => $client->id,
        'estado' => 'en_proceso',
        'codigo' => 'OS-2026-0002',
    ]);

    $this->actingAs($this->tecnicoPlanta)
        ->get(route('tecnico-planta.dashboard', ['current_team' => $this->team]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tecnico-planta/dashboard')
            ->has('kpis', fn (Assert $kpis) => $kpis
                ->where('pendientes_recepcion', 1)
                ->where('en_taller', 1)
                ->where('esperando_autorizacion', 0)
                ->where('listas', 0)
            )
            ->where('filters.tab', 'todas')
            ->has('orders.data', 2)
        );
});

test('tecnico planta dashboard filters by queue tabs and search', function () {
    $client = Client::factory()->create(['razon_social' => 'Acme Corporation S.A.C.']);

    ServiceOrder::factory()->create([
        'client_id' => $client->id,
        'estado' => 'en_proceso',
        'codigo' => 'OS-2026-9999',
    ]);

    // Query tab en_taller
    $this->actingAs($this->tecnicoPlanta)
        ->get(route('tecnico-planta.dashboard', [
            'current_team' => $this->team,
            'tab' => 'en_taller',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.tab', 'en_taller')
            ->has('orders.data', 1)
            ->where('orders.data.0.codigo', 'OS-2026-9999')
        );

    // Search matches
    $this->actingAs($this->tecnicoPlanta)
        ->get(route('tecnico-planta.dashboard', [
            'current_team' => $this->team,
            'tab' => 'en_taller',
            'search' => 'Acme',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('orders.data', 1)
        );

    // Search doesn't match
    $this->actingAs($this->tecnicoPlanta)
        ->get(route('tecnico-planta.dashboard', [
            'current_team' => $this->team,
            'tab' => 'en_taller',
            'search' => 'NonExistent',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('orders.data', 0)
        );
});
