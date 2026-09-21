<?php

use App\Enums\TeamRole;
use App\Models\Client;
use App\Models\Deficiency;
use App\Models\ServiceOrder;
use App\Models\ServiceOrderEvent;
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

test('tecnico planta can list deficiencias with counters and filter by state', function () {
    $client = Client::factory()->create();
    $order = ServiceOrder::factory()->create([
        'client_id' => $client->id,
        'codigo' => 'OS-DEF-0001',
    ]);

    Deficiency::create([
        'service_order_id' => $order->id,
        'componente' => 'Válvula de Descarga',
        'condicion' => 'Fuga en el vástago',
        'requiere_autorizacion' => true,
        'estado' => 'esperando_autorizacion',
    ]);

    $this->actingAs($this->tecnicoPlanta)
        ->get(route('tecnico-planta.deficiencias.index', ['current_team' => $this->team]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tecnico-planta/deficiencias/index')
            ->has('deficiencies.data', 1)
            ->where('counts.esperando_autorizacion', 1)
        );
});

test('storing deficiency requiring authorization updates order state and notifies seller', function () {
    $client = Client::factory()->create();
    $order = ServiceOrder::factory()->create([
        'client_id' => $client->id,
        'codigo' => 'OS-DEF-0002',
        'estado' => 'recibido_planta',
    ]);

    $this->actingAs($this->tecnicoPlanta)
        ->post(route('tecnico-planta.deficiencias.store', [
            'current_team' => $this->team,
            'service_order' => $order,
        ]), [
            'componente' => 'Manguera de Descarga',
            'condicion' => 'Malla metálica expuesta y rota',
            'accion_recomendada' => 'Cambio de manguera 1/2 pulgada',
            'repuesto_sugerido' => 'Manguera de alta presión',
            'requiere_autorizacion' => true,
            'nota' => 'Riesgo de rotura al disparar',
        ])
        ->assertRedirect();

    $order->refresh();
    expect($order->estado)->toBe('esperando_autorizacion');

    $deficiency = Deficiency::where('service_order_id', $order->id)->first();
    expect($deficiency)->not->toBeNull()
        ->and($deficiency->componente)->toBe('Manguera de Descarga')
        ->and($deficiency->estado)->toBe('esperando_autorizacion')
        ->and($deficiency->requiere_autorizacion)->toBeTrue();

    // Eventos generados para Vendedor
    expect(ServiceOrderEvent::where('service_order_id', $order->id)->where('tipo', 'deficiencia_detectada')->exists())->toBeTrue()
        ->and(ServiceOrderEvent::where('service_order_id', $order->id)->where('tipo', 'notificacion_vendedor')->exists())->toBeTrue();
});

test('tecnico planta can resolve a deficiency in workshop', function () {
    $client = Client::factory()->create();
    $order = ServiceOrder::factory()->create([
        'client_id' => $client->id,
        'codigo' => 'OS-DEF-0003',
        'estado' => 'en_proceso',
    ]);

    $deficiency = Deficiency::create([
        'service_order_id' => $order->id,
        'componente' => 'Pasador de Seguridad',
        'condicion' => 'Faltante',
        'requiere_autorizacion' => false,
        'estado' => 'detectada',
    ]);

    $this->actingAs($this->tecnicoPlanta)
        ->post(route('tecnico-planta.deficiencias.resolve', [
            'current_team' => $this->team,
            'deficiency' => $deficiency,
        ]), [
            'resolucion' => 'Se colocó pasador nuevo con precinto de seguridad color amarillo',
        ])
        ->assertRedirect();

    $deficiency->refresh();
    expect($deficiency->estado)->toBe('resuelta')
        ->and($deficiency->resolucion)->toContain('Se colocó pasador nuevo');

    expect(ServiceOrderEvent::where('service_order_id', $order->id)->where('tipo', 'otro')->exists())->toBeTrue();
});
