<?php

use App\Models\ServiceOrder;
use App\Models\ServiceOrderEvent;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

it('rechaza una entrega sin conformidad del cliente y no cambia el estado', function () {
    $tecnico = User::factory()->create();
    $tecnico->assignRole('TecnicoCampo');
    $order = ServiceOrder::factory()->create([
        'departamento_tecnico' => 'campo',
        'estado' => 'listo_entrega',
    ]);

    $this->actingAs($tecnico)
        ->post(route('tecnico-campo.entregas.confirm', [
            'current_team' => $tecnico->currentTeam,
            'service_order' => $order,
        ]), [
            'receptor_nombre' => 'Cliente QA',
            'conformidad_aceptada' => false,
        ])
        ->assertSessionHasErrors('conformidad_aceptada');

    expect($order->fresh()->estado)->toBe('listo_entrega')
        ->and(ServiceOrderEvent::query()
            ->whereBelongsTo($order)
            ->where('payload->accion', 'entrega_final_realizada')
            ->exists())->toBeFalse();
});

it('registra la entrega conforme como acta en la bitácora y cierra la orden', function () {
    $tecnico = User::factory()->create();
    $tecnico->assignRole('TecnicoCampo');
    $order = ServiceOrder::factory()->create([
        'departamento_tecnico' => 'campo',
        'estado' => 'listo_entrega',
    ]);

    $this->actingAs($tecnico)
        ->post(route('tecnico-campo.entregas.confirm', [
            'current_team' => $tecnico->currentTeam,
            'service_order' => $order,
        ]), [
            'receptor_nombre' => 'Cliente QA',
            'receptor_dni' => '12345678',
            'conformidad_aceptada' => true,
            'cerrar_orden' => true,
        ])
        ->assertSessionHasNoErrors();

    $event = ServiceOrderEvent::query()
        ->whereBelongsTo($order)
        ->where('payload->accion', 'entrega_final_realizada')
        ->firstOrFail();

    expect($order->fresh()->estado)->toBe('cerrado')
        ->and($event->payload['receptor_nombre'])->toBe('Cliente QA')
        ->and($event->payload['eslabon_custodia'])->toBe('entrega_campo');
});

it('debe impedir descargar un acta antes de registrar la entrega', function () {
    $tecnico = User::factory()->create();
    $tecnico->assignRole('TecnicoCampo');
    $order = ServiceOrder::factory()->create([
        'departamento_tecnico' => 'campo',
        'estado' => 'listo_entrega',
    ]);

    $this->actingAs($tecnico)
        ->get(route('tecnico-campo.entregas.pdf', [
            'current_team' => $tecnico->currentTeam,
            'service_order' => $order,
        ]))
        ->assertNotFound();
});

it('debe impedir saltar estados técnicos de planta', function () {
    $tecnico = User::factory()->create();
    $tecnico->assignRole('TecnicoPlanta');
    $order = ServiceOrder::factory()->create([
        'departamento_tecnico' => 'planta',
        'estado' => 'pendiente_recepcion',
    ]);

    $this->actingAs($tecnico)
        ->post(route('tecnico-planta.ejecucion.advance', [
            'current_team' => $tecnico->currentTeam,
            'service_order' => $order,
        ]), ['target_state' => 'listo_entrega'])
        ->assertSessionHasErrors('target_state');

    expect($order->fresh()->estado)->toBe('pendiente_recepcion');
});
