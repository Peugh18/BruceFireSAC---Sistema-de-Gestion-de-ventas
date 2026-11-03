<?php

use App\Models\Equipment;
use App\Models\ServiceOrder;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('mostrador reconoce entrega de campo y no permite repetirla', function () {
    $user = vendedorUser();
    $order = ServiceOrder::factory()->create(['sede_id' => $user->sede_id, 'estado' => 'entregado']);
    $order->events()->create(['tipo' => 'trabajo_completado', 'user_id' => $user->id, 'payload' => ['accion' => 'entrega_final_realizada', 'eslabon_custodia' => 'entrega_campo', 'receptor_nombre' => 'Receptor campo']]);
    $params = ['current_team' => $user->currentTeam, 'service_order' => $order];
    $this->actingAs($user)->get(route('vendedor.ordenes-servicio.entrega-mostrador.show', $params))
        ->assertOk()->assertInertia(fn ($page) => $page->where('delivery.receptor_nombre', 'Receptor campo'));
    $this->actingAs($user)->post(route('vendedor.ordenes-servicio.entrega-mostrador.store', $params), ['receptor_nombre' => 'Segundo receptor', 'receptor_dni' => '12345678', 'conformidad_aceptada' => true])->assertUnprocessable();
    expect($order->events()->count())->toBe(1)->and($order->fresh()->estado)->toBe('entregado');
});

test('al entregar en mostrador el extintor deja de figurar en el taller', function () {
    $user = vendedorUser();
    $order = ServiceOrder::factory()->create(['sede_id' => $user->sede_id, 'estado' => 'listo_entrega']);
    $equipo = Equipment::factory()->create(['client_id' => $order->client_id, 'ubicacion_actual' => 'Planta - Taller']);
    $order->equipments()->attach($equipo, ['recibido' => true]);

    $this->actingAs($user)->post(route('vendedor.ordenes-servicio.entrega-mostrador.store', ['current_team' => $user->currentTeam, 'service_order' => $order]), [
        'receptor_nombre' => 'Receptor', 'receptor_dni' => '12345678', 'conformidad_aceptada' => true,
    ])->assertSessionHasNoErrors();

    expect($equipo->fresh()->ubicacion_actual)->toBe('Sede cliente');
});
