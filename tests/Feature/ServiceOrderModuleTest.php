<?php

use App\Models\Client;
use App\Models\ServiceOrder;
use App\Models\ServiceOrderEvent;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('crear service order standalone genera codigo y evento inicial', function () {
    $user = vendedorUser();
    $client = Client::factory()->create();

    $this->actingAs($user)
        ->post(route('vendedor.ordenes-servicio.store', ['current_team' => $user->currentTeam]), [
            'client_id' => $client->id,
            'tipo_servicio' => 'Recarga y mantenimiento',
            'fecha' => now()->toDateString(),
            'departamento_tecnico' => 'planta',
        ])
        ->assertSessionHasNoErrors();

    $order = ServiceOrder::firstOrFail();

    expect($order->codigo)->toBe('OT-'.now()->year.'-0001')
        ->and($order->estado)->toBe('pendiente_recepcion')
        ->and(ServiceOrderEvent::where('service_order_id', $order->id)->where('tipo', 'creada')->exists())->toBeTrue();
});

test('comunicacion no incluye ordenes de campo', function () {
    $user = vendedorUser();
    $planta = ServiceOrder::factory()->create(['departamento_tecnico' => 'planta']);
    $campo = ServiceOrder::factory()->create(['departamento_tecnico' => 'campo']);
    ServiceOrderEvent::factory()->create(['service_order_id' => $planta->id]);
    ServiceOrderEvent::factory()->create(['service_order_id' => $campo->id]);

    $response = $this->actingAs($user)
        ->get(route('vendedor.comunicacion.index', ['current_team' => $user->currentTeam]))
        ->assertOk();

    $orders = $response->viewData('page')['props']['orders']['data'];

    expect(collect($orders)->pluck('id')->all())
        ->toContain($planta->id)
        ->not->toContain($campo->id);
});
