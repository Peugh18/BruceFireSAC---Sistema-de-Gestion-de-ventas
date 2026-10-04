<?php

use App\Enums\TeamRole;
use App\Models\Client;
use App\Models\Deficiency;
use App\Models\Equipment;
use App\Models\Product;
use App\Models\ServiceOrder;
use App\Models\ServiceOrderEvent;
use App\Models\Team;
use App\Models\User;
use App\Services\Avisos\AvisosDelVendedor;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->team = Team::factory()->create();
    $this->tecnico = User::factory()->create(['current_team_id' => $this->team->id]);
    $this->team->members()->attach($this->tecnico, ['role' => TeamRole::Admin->value]);
    $this->tecnico->assignRole('TecnicoPlanta');
    $this->vendedor = User::factory()->create();
    $this->vendedor->assignRole('Vendedor');
});

function entregarEnMostrador(ServiceOrder $order)
{
    return test()->actingAs(test()->vendedor)->post(route('vendedor.ordenes-servicio.entrega-mostrador.store', [
        'current_team' => test()->vendedor->currentTeam,
        'service_order' => $order,
    ]), ['receptor_nombre' => 'Cliente', 'receptor_dni' => '12345678', 'conformidad_aceptada' => true]);
}

test('con el certificado emitido el vendedor ve el aviso y entrega una sola vez sin volver a mover las fechas', function () {
    $order = ServiceOrder::factory()->create(['sede_id' => $this->vendedor->sede_id, 'estado' => 'listo_certificado']);
    $equipo = Equipment::factory()->create(['client_id' => $order->client_id, 'proxima_fecha_atencion' => now()->addYear()->subDays(10)->toDateString()]);
    $order->equipments()->attach($equipo->id);
    ServiceOrderEvent::create(['service_order_id' => $order->id, 'tipo' => 'trabajo_completado', 'user_id' => $this->tecnico->id, 'payload' => ['accion' => 'cambio_estado_planta']]);

    expect(json_encode(app(AvisosDelVendedor::class)->lista($this->vendedor, $this->vendedor->currentTeam->slug)))
        ->toContain($order->codigo);

    entregarEnMostrador($order)->assertSessionHasNoErrors();

    expect($order->fresh()->estado)->toBe('cerrado')
        ->and($equipo->fresh()->proxima_fecha_atencion->toDateString())->toBe(now()->addYear()->subDays(10)->toDateString());

    $order->update(['estado' => 'listo_entrega']);
    entregarEnMostrador($order)->assertStatus(422);
});

test('el tecnico pasa la orden de certificado emitido a lista para entrega', function () {
    $order = ServiceOrder::factory()->create(['estado' => 'listo_certificado', 'sede_id' => $this->tecnico->sede_id]);

    $this->actingAs($this->tecnico)
        ->post(route('tecnico-planta.ejecucion.advance', ['current_team' => $this->team, 'service_order' => $order]), ['target_state' => 'listo_entrega'])
        ->assertSessionHasNoErrors();

    expect($order->fresh()->estado)->toBe('listo_entrega');
});

test('no se gasta repuesto en una reparacion sin autorizar, rechazada o de otra orden', function () {
    $order = ServiceOrder::factory()->create(['estado' => 'en_proceso', 'sede_id' => $this->tecnico->sede_id]);
    $otra = ServiceOrder::factory()->create(['estado' => 'en_proceso', 'sede_id' => $this->tecnico->sede_id]);
    $product = Product::factory()->create();
    $consumir = fn (ServiceOrder $orden, Deficiency $deficiencia) => $this->actingAs($this->tecnico)
        ->post(route('tecnico-planta.ejecucion.consume-spare', ['current_team' => $this->team, 'service_order' => $orden, 'deficiency' => $deficiencia]), ['product_id' => $product->id, 'cantidad' => 1]);

    foreach (['esperando_autorizacion', 'rechazada'] as $estado) {
        $deficiencia = Deficiency::create(['service_order_id' => $order->id, 'componente' => 'Manómetro', 'condicion' => 'Roto', 'estado' => $estado]);
        $consumir($order, $deficiencia)->assertSessionHasErrors('deficiency');
        expect($deficiencia->fresh()->estado)->toBe($estado);
    }

    $ajena = Deficiency::create(['service_order_id' => $otra->id, 'componente' => 'Válvula', 'condicion' => 'Fuga', 'estado' => 'autorizada']);
    $consumir($order, $ajena)->assertSessionHasErrors('deficiency');
    expect($ajena->fresh()->estado)->toBe('autorizada');
});

test('al autorizar la ultima deficiencia la orden sale de esperando autorizacion', function () {
    $order = ServiceOrder::factory()->create(['estado' => 'esperando_autorizacion', 'sede_id' => $this->vendedor->sede_id]);
    $deficiencia = Deficiency::create(['service_order_id' => $order->id, 'componente' => 'Manguera', 'condicion' => 'Rajada', 'estado' => 'esperando_autorizacion', 'requiere_autorizacion' => true]);

    $this->actingAs($this->vendedor)
        ->post(route('vendedor.deficiencias.autorizar', ['current_team' => $this->vendedor->currentTeam, 'deficiency' => $deficiencia]), [
            'autorizado' => true, 'autorizado_por' => 'Jefe de planta', 'canal' => 'whatsapp', 'fecha' => today()->toDateString(),
        ])
        ->assertSessionHasNoErrors();

    expect($deficiencia->fresh()->estado)->toBe('autorizada')
        ->and($order->fresh()->estado)->toBe('autorizado');
});

test('el checklist solo se hace sobre extintores de la orden', function () {
    $order = ServiceOrder::factory()->create(['estado' => 'recibido_planta', 'sede_id' => $this->tecnico->sede_id]);
    $deOtroCliente = Equipment::factory()->create(['client_id' => Client::factory()->create()->id, 'estado' => 'activo']);

    $this->actingAs($this->tecnico)
        ->post(route('tecnico-planta.checklist.store', ['current_team' => $this->team, 'service_order' => $order, 'equipment' => $deOtroCliente]), [
            'items' => ['manometro' => ['estado' => 'observado', 'condicion' => 'Roto']],
        ])
        ->assertSessionHasErrors('equipment');

    expect(Deficiency::count())->toBe(0)
        ->and($deOtroCliente->fresh()->estado)->toBe('activo');
});
