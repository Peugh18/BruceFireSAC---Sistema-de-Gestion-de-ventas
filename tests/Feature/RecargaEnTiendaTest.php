<?php

use App\Actions\TecnicoPlanta\ExecuteAndCloseServiceOrder;
use App\Enums\TeamRole;
use App\Models\Client;
use App\Models\Deficiency;
use App\Models\DeficiencyAuthorization;
use App\Models\Equipment;
use App\Models\Product;
use App\Models\Service;
use App\Models\ServiceOrder;
use App\Models\Team;
use App\Models\User;
use App\Services\ServiceOrders\ServiceOrderNumberGenerator;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('vendedor registra un extintor existente y uno nuevo sin duplicarlos en planta', function () {
    $seller = vendedorUser();
    $client = Client::factory()->create();
    $order = ServiceOrder::factory()->create(['client_id' => $client->id, 'sede_id' => $seller->sede_id]);
    $existing = Equipment::factory()->create(['client_id' => $client->id, 'numero_serie' => 'BF-EQ-004200']);

    foreach ([['numero_serie' => $existing->numero_serie], ['tipo_agente' => 'PQS ABC', 'capacidad' => '6 kg']] as $payload) {
        $this->actingAs($seller)->post(route('vendedor.ordenes-servicio.equipos.store', ['current_team' => $seller->currentTeam, 'service_order' => $order]), $payload)->assertRedirect();
    }
    expect($order->equipments()->count())->toBe(2);

    $technician = User::factory()->create(['current_team_id' => $seller->current_team_id]);
    $technician->assignRole('TecnicoPlanta');
    $seller->currentTeam->members()->attach($technician, ['role' => TeamRole::Admin->value]);
    $this->actingAs($technician)->post(route('tecnico-planta.recepciones.confirm', ['current_team' => $seller->currentTeam, 'service_order' => $order]), ['equipos_recibidos_count' => 2])->assertRedirect();
    expect($order->equipments()->count())->toBe(2)->and($order->equipments()->wherePivot('recibido', true)->count())->toBe(2);
});

test('un extintor no entra a una segunda orden abierta', function () {
    $seller = vendedorUser();
    $client = Client::factory()->create();
    $equipment = Equipment::factory()->create(['client_id' => $client->id, 'numero_serie' => 'BF-EQ-004300']);
    $abierta = ServiceOrder::factory()->create(['client_id' => $client->id, 'sede_id' => $seller->sede_id, 'estado' => 'en_proceso']);
    $abierta->equipments()->attach($equipment, ['recibido' => true]);
    $nueva = ServiceOrder::factory()->create(['client_id' => $client->id, 'sede_id' => $seller->sede_id]);

    $this->actingAs($seller)->post(route('vendedor.ordenes-servicio.equipos.store', ['current_team' => $seller->currentTeam, 'service_order' => $nueva]), ['numero_serie' => 'BF-EQ-004300'])->assertSessionHasErrors('numero_serie');
    expect($nueva->equipments()->count())->toBe(0);

    $abierta->update(['estado' => 'entregado']);
    $this->actingAs($seller)->post(route('vendedor.ordenes-servicio.equipos.store', ['current_team' => $seller->currentTeam, 'service_order' => $nueva]), ['numero_serie' => 'BF-EQ-004300'])->assertSessionHasNoErrors();
    expect($nueva->equipments()->count())->toBe(1);
});

test('constancia pdf usa la lista de extintores de la orden', function () {
    $seller = vendedorUser();
    $order = ServiceOrder::factory()->create(['sede_id' => $seller->sede_id]);
    $equipment = Equipment::factory()->create(['client_id' => $order->client_id, 'numero_serie' => 'BF-EQ-009999']);
    $order->equipments()->attach($equipment, ['recibido' => false]);

    $response = $this->actingAs($seller)->get(route('vendedor.ordenes-servicio.constancia-recepcion', ['current_team' => $seller->currentTeam, 'service_order' => $order]));
    $response->assertOk();
    expect($response->headers->get('content-type'))->toBe('application/pdf')->and($response->getContent())->not->toBeEmpty();
});

test('cerrar trabajo renueva la proxima fecha de atencion', function () {
    $user = vendedorUser();
    $order = ServiceOrder::factory()->create(['estado' => 'datos_completos']);
    $equipment = Equipment::factory()->create(['client_id' => $order->client_id, 'proxima_fecha_atencion' => now()->subDay()]);
    $order->equipments()->attach($equipment, ['recibido' => true]);

    app(ExecuteAndCloseServiceOrder::class)->advanceState($order, 'listo_certificado', $user);
    expect($equipment->refresh()->proxima_fecha_atencion->toDateString())->toBe(now()->addYear()->toDateString());
});

test('cobrar vincula la venta y rechaza un segundo cobro', function () {
    $seller = vendedorUser();
    $order = ServiceOrder::factory()->create(['sede_id' => $seller->sede_id]);
    $equipment = Equipment::factory()->create(['client_id' => $order->client_id]);
    $order->equipments()->attach($equipment, ['recibido' => false]);
    $service = Service::factory()->create(['precio_venta' => 50]);
    $payload = ['service_order_id' => $order->id, 'client_id' => $order->client_id, 'sede_id' => $seller->sede_id, 'fecha' => now()->toDateString(), 'destino' => 'local_cliente', 'condicion_pago' => 'contado', 'medio_pago' => 'efectivo', 'comprobante_tipo' => 'factura', 'items' => [['tipo_linea' => 'recarga_servicio', 'numero_serie' => $equipment->numero_serie, 'service_id' => $service->id, 'cantidad' => 1, 'precio_unitario' => 50]]];

    $this->actingAs($seller)->post(route('vendedor.ventas.store', ['current_team' => $seller->currentTeam]), $payload)->assertRedirect();
    expect($order->refresh()->sale_id)->not->toBeNull();
    $this->actingAs($seller)->from(route('vendedor.ventas.create', ['current_team' => $seller->currentTeam]))->post(route('vendedor.ventas.store', ['current_team' => $seller->currentTeam]), $payload)->assertSessionHasErrors('service_order_id');
});

test('cobrar avisa el adicional ya resuelto que se aprobo sin cotizacion', function () {
    $seller = vendedorUser();
    $order = ServiceOrder::factory()->create(['sede_id' => $seller->sede_id, 'estado' => 'listo_entrega']);
    $deficiency = Deficiency::factory()->create(['service_order_id' => $order->id, 'componente' => 'Cilindro', 'estado' => 'resuelta']);
    DeficiencyAuthorization::factory()->create(['deficiency_id' => $deficiency->id, 'importe' => 35]);

    $this->actingAs($seller)->get(route('vendedor.ventas.create', ['current_team' => $seller->currentTeam, 'orden_servicio' => $order->id]))
        ->assertInertia(fn (Assert $page) => $page->where('venta.adicionales_sin_cotizacion', [['componente' => 'Cilindro', 'importe' => 35]]));
});

test('no se cobra una orden anulada', function () {
    $seller = vendedorUser();
    $order = ServiceOrder::factory()->create(['sede_id' => $seller->sede_id, 'estado' => 'anulada']);
    $service = Service::factory()->create(['precio_venta' => 50]);
    $payload = ['service_order_id' => $order->id, 'client_id' => $order->client_id, 'sede_id' => $seller->sede_id, 'fecha' => now()->toDateString(), 'destino' => 'local_cliente', 'condicion_pago' => 'contado', 'medio_pago' => 'efectivo', 'comprobante_tipo' => 'factura', 'items' => [['tipo_linea' => 'servicio', 'service_id' => $service->id, 'cantidad' => 1, 'precio_unitario' => 50]]];

    $this->actingAs($seller)->post(route('vendedor.ventas.store', ['current_team' => $seller->currentTeam]), $payload)->assertSessionHasErrors('service_order_id');
    expect($order->refresh()->sale_id)->toBeNull();
});

test('entrega avisa cuando la orden no esta cobrada', function () {
    $seller = vendedorUser();
    $order = ServiceOrder::factory()->create(['sede_id' => $seller->sede_id, 'estado' => 'listo_entrega', 'sale_id' => null]);
    $this->actingAs($seller)->get(route('vendedor.ordenes-servicio.entrega-mostrador.show', ['current_team' => $seller->currentTeam, 'service_order' => $order]))->assertInertia(fn (Assert $page) => $page->where('isPaid', false));
});

test('numerador de orden no reutiliza codigos borrados', function () {
    $generator = app(ServiceOrderNumberGenerator::class);
    $first = $generator->next();
    ServiceOrder::factory()->create(['codigo' => $first])->delete();
    expect($generator->next())->not->toBe($first);
});

test('migracion elimina equipment id de ordenes de servicio', function () {
    expect(Schema::hasColumn('service_orders', 'equipment_id'))->toBeFalse();
});

test('stock de almacen no expone servicios', function () {
    $team = Team::factory()->create();
    $user = User::factory()->create(['current_team_id' => $team->id]);
    $team->members()->attach($user, ['role' => TeamRole::Admin->value]);
    $user->assignRole('Almacen');
    Product::factory()->create(['activo' => true]);
    Service::factory()->create(['activo' => true]);
    $this->actingAs($user)->get(route('almacen.stock.index', ['current_team' => $team]))->assertInertia(fn (Assert $page) => $page->where('items.total', 1)->missing('kpis.total_servicios'));
});
