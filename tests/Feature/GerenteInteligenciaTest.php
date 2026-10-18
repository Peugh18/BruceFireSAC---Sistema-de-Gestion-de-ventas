<?php

use App\Enums\TeamRole;
use App\Models\Certificate;
use App\Models\Client;
use App\Models\ClientRetentionScore;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->gerente = User::factory()->create();
    $this->gerente->assignRole('Gerente');
    $this->team = Team::factory()->create();
    $this->team->members()->attach($this->gerente, ['role' => TeamRole::Admin->value]);
});

test('inteligencia muestra sin datos cuando no hay puntuaciones', function () {
    $this->actingAs($this->gerente)->get(route('gerente.inteligencia', $this->team))
        ->assertOk()->assertInertia(fn (Assert $page) => $page->component('gerente/inteligencia')
        ->where('resumen.total', 0)->where('resumen.calculado', null)->has('clientes.data', 0));
});

test('inteligencia reutiliza puntuacion historica y separa vencimientos reales de estimaciones', function () {
    $this->freezeTime();
    $client = Client::factory()->create(['razon_social' => 'Empresa con historial']);
    ClientRetentionScore::query()->create([
        'client_id' => $client->id, 'probabilidad' => 0.82, 'categoria' => 'alta',
        'recencia_dias' => 20, 'frecuencia_compras' => 5, 'monto_total' => 1500,
        'ticket_promedio' => 300, 'antiguedad_dias' => 360, 'diversidad_productos' => 2,
        'compro_recarga' => true, 'scored_at' => now()->subDays(31),
        'factores_json' => ['resumen' => 'Compras recurrentes'],
    ]);
    Certificate::factory()->create(['client_id' => $client->id, 'fecha_vigencia_hasta' => today()->addDays(10)]);
    Certificate::factory()->create(['client_id' => $client->id, 'estado' => 'anulado', 'fecha_vigencia_hasta' => today()->addDay()]);
    $this->actingAs($this->gerente)->get(route('gerente.inteligencia', $this->team))
        ->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('resumen.total', 1)->where('resumen.desactualizados', 1)
        ->where('clientes.data.0.porcentaje', 82)->where('clientes.data.0.resumen', 'Compras recurrentes')
        ->where('clientes.data.0.desactualizado', true)->where('clientes.data.0.compras', 5)
        ->where('vencimientos.0.total', 1)->where('vencimientos.1.total', 0));
});

test('inteligencia filtra por cliente categoria y vigencia del calculo sin perder paginacion', function () {
    $this->freezeTime();
    $clients = Client::factory()->count(21)->create(['razon_social' => 'Cliente objetivo']);
    foreach ($clients as $client) {
        ClientRetentionScore::query()->create([
            'client_id' => $client->id, 'probabilidad' => 0.8, 'categoria' => 'alta',
            'recencia_dias' => 1, 'frecuencia_compras' => 2, 'monto_total' => 200,
            'ticket_promedio' => 100, 'antiguedad_dias' => 100, 'diversidad_productos' => 1,
            'compro_recarga' => false, 'scored_at' => now(),
        ]);
    }
    $url = route('gerente.inteligencia', ['current_team' => $this->team, 'buscar' => 'objetivo', 'categoria' => 'alta', 'vigencia' => 'actualizados']);
    $this->actingAs($this->gerente)->get($url)->assertInertia(fn (Assert $page) => $page
        ->has('clientes.data', 20)->where('clientes.total', 21)
        ->where('clientes.next_page_url', fn ($url) => str_contains($url, 'buscar=objetivo') && str_contains($url, 'page=2')));
    $this->get(route('gerente.inteligencia', ['current_team' => $this->team, 'categoria' => 'baja']))
        ->assertInertia(fn (Assert $page) => $page->has('clientes.data', 0));
    $this->get(route('gerente.inteligencia', ['current_team' => $this->team, 'vigencia' => 'desactualizados']))
        ->assertInertia(fn (Assert $page) => $page->has('clientes.data', 0));
});

test('inteligencia exige rol gerente y pertenencia al equipo', function () {
    $vendedor = User::factory()->create();
    $vendedor->assignRole('Vendedor');
    $this->team->members()->attach($vendedor, ['role' => TeamRole::Member->value]);
    $this->actingAs($vendedor)->get(route('gerente.inteligencia', $this->team))->assertForbidden();
    $otherTeam = Team::factory()->create();
    $this->actingAs($this->gerente)->get(route('gerente.inteligencia', $otherTeam))->assertForbidden();
});
