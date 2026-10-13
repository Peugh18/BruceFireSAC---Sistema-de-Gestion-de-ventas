<?php

use App\Models\Client;
use App\Models\Product;
use App\Models\Quote;
use App\Models\Sale;
use App\Models\ServiceOrder;
use App\Models\Vehicle;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

/** @return array<string, mixed> */
function datosCotizacionDestino(Client $client): array
{
    $product = Product::factory()->create(['precio_venta' => 100]);

    return [
        'client_id' => $client->id,
        'fecha' => today()->toDateString(),
        'vigencia_hasta' => today()->addDays(15)->toDateString(),
        'condicion_pago_propuesta' => 'Contado',
        'items' => [['product_id' => $product->id, 'cantidad' => 1, 'precio_unitario' => 100]],
    ];
}

test('la cotizacion conserva el destino elegido aunque la referencia parezca una placa', function (string $destino) {
    $user = vendedorUser();
    $client = Client::factory()->create();

    $this->actingAs($user)->post(route('vendedor.cotizaciones.store', $user->currentTeam), [
        ...datosCotizacionDestino($client), 'destino' => $destino, 'referencia' => 'AVR-213',
    ])->assertSessionHasNoErrors()->assertRedirect();

    $quote = Quote::sole();
    expect($quote->destino)->toBe($destino)
        ->and($quote->referencia)->toBe('AVR-213')
        ->and($quote->vehicle_id)->toBeNull();
    $this->assertDatabaseCount('vehicles', 0);

    $this->get(route('vendedor.ventas.create', [$user->currentTeam, 'cotizacion' => $quote->id]))
        ->assertInertia(fn ($page) => $page->where('quote.destino', $destino)->where('quote.referencia', 'AVR-213')->where('quote.vehicle_id', null));
})->with(['vehiculo', 'local_cliente']);

test('seleccionar una placa propia conserva su vehiculo en la cotizacion', function () {
    $user = vendedorUser();
    $client = Client::factory()->create();
    $vehicle = Vehicle::factory()->create(['client_id' => $client->id, 'placa' => 'AVR-213']);

    $this->actingAs($user)->post(route('vendedor.cotizaciones.store', $user->currentTeam), [
        ...datosCotizacionDestino($client), 'destino' => 'vehiculo', 'vehicle_id' => $vehicle->id, 'referencia' => 'PLACA: AVR-213',
    ])->assertSessionHasNoErrors();

    expect(Quote::sole()->vehicle_id)->toBe($vehicle->id);
});

test('cotizar rechaza la placa de otro cliente sin guardar una cotizacion', function () {
    $user = vendedorUser();
    $client = Client::factory()->create();
    $vehicle = Vehicle::factory()->create();

    $this->actingAs($user)->post(route('vendedor.cotizaciones.store', $user->currentTeam), [
        ...datosCotizacionDestino($client), 'destino' => 'vehiculo', 'vehicle_id' => $vehicle->id,
    ])->assertSessionHasErrors(['vehicle_id' => 'El vehículo elegido no es de este cliente.']);

    $this->assertDatabaseCount('quotes', 0);
});

test('cotizar rechaza destinos desconocidos', function () {
    $user = vendedorUser();
    $client = Client::factory()->create();

    $this->actingAs($user)->post(route('vendedor.cotizaciones.store', $user->currentTeam), [
        ...datosCotizacionDestino($client), 'destino' => 'otro',
    ])->assertSessionHasErrors(['destino' => 'Elige local o vehículo.']);

    $this->assertDatabaseCount('quotes', 0);
});

test('las cotizaciones anteriores reconocen placas sin inventar vehiculos ni convertir texto libre', function (?string $referencia, string $destino) {
    $user = vendedorUser();
    $quote = Quote::factory()->create(['referencia' => $referencia, 'vehicle_id' => null]);

    $this->actingAs($user)->get(route('vendedor.ventas.create', [$user->currentTeam, 'cotizacion' => $quote->id]))
        ->assertInertia(fn ($page) => $page->where('quote.destino', $destino)->where('quote.referencia', $referencia)->where('quote.vehicle_id', null));

    $this->assertDatabaseCount('vehicles', 0);
})->with([
    'placa simple' => ['AVR-213', 'vehiculo'],
    'placa rotulada' => ['PLACA: B32-928', 'vehiculo'],
    'placa sin guion' => ['AVR213', 'vehiculo'],
    'texto libre' => ['Entrega en oficina', 'local_cliente'],
    'referencia de seis letras' => ['CENTRO', 'local_cliente'],
    'referencia numerica' => ['123456', 'local_cliente'],
    'sede' => ['SEDE: Oficina Chimbote', 'local_cliente'],
    'sin referencia' => [null, 'local_cliente'],
]);

test('la cotizacion anterior con vehiculo conserva el destino aunque no tenga referencia', function () {
    $user = vendedorUser();
    $client = Client::factory()->create();
    $vehicle = Vehicle::factory()->create(['client_id' => $client->id]);
    $quote = Quote::factory()->create(['client_id' => $client->id, 'vehicle_id' => $vehicle->id]);

    $this->actingAs($user)->get(route('vendedor.ventas.create', [$user->currentTeam, 'cotizacion' => $quote->id]))
        ->assertInertia(fn ($page) => $page->where('quote.destino', 'vehiculo')->where('quote.vehicle_id', $vehicle->id));
});

test('editar la cotizacion recupera y puede cambiar su destino y placa', function () {
    $user = vendedorUser();
    $client = Client::factory()->create();
    $vehicle = Vehicle::factory()->create(['client_id' => $client->id]);
    $quote = Quote::factory()->create(['client_id' => $client->id, 'vehicle_id' => $vehicle->id, 'referencia' => 'PLACA: AVR-213']);

    $this->actingAs($user)->get(route('vendedor.cotizaciones.edit', [$user->currentTeam, $quote]))
        ->assertInertia(fn ($page) => $page->where('edicion.destino', 'vehiculo')->where('edicion.vehicle_id', $vehicle->id));

    $this->put(route('vendedor.cotizaciones.update', [$user->currentTeam, $quote]), [
        ...datosCotizacionDestino($client), 'destino' => 'local_cliente', 'vehicle_id' => null, 'referencia' => 'SEDE: Central',
    ])->assertSessionHasNoErrors();

    expect($quote->refresh()->destino)->toBe('local_cliente')
        ->and($quote->vehicle_id)->toBeNull()
        ->and($quote->referencia)->toBe('SEDE: Central');
});

test('renovar una cotizacion vencida recupera su destino y vehiculo', function () {
    $user = vendedorUser();
    $client = Client::factory()->create();
    $vehicle = Vehicle::factory()->create(['client_id' => $client->id]);
    $quote = Quote::factory()->vencida()->create(['client_id' => $client->id, 'vehicle_id' => $vehicle->id, 'referencia' => 'PLACA: AVR-213']);

    $this->actingAs($user)->get(route('vendedor.cotizaciones.create', [$user->currentTeam, 'renovar' => $quote->id]))
        ->assertInertia(fn ($page) => $page->where('renovacion.destino', 'vehiculo')->where('renovacion.vehicle_id', $vehicle->id)->where('renovacion.referencia', 'PLACA: AVR-213'));
});

test('reanudar un borrador de venta conserva el vehiculo seleccionado', function () {
    $user = vendedorUser();
    $client = Client::factory()->create();
    $vehicle = Vehicle::factory()->create(['client_id' => $client->id]);
    $sale = Sale::factory()->create(['vendedor_id' => $user->id, 'client_id' => $client->id, 'vehicle_id' => $vehicle->id, 'destino' => 'vehiculo', 'referencia' => 'PLACA: AVR-213']);

    $this->actingAs($user)->get(route('vendedor.ventas.edit', [$user->currentTeam, $sale]))
        ->assertInertia(fn ($page) => $page->where('venta.destino', 'vehiculo')->where('venta.vehicle_id', $vehicle->id)->where('venta.referencia', 'PLACA: AVR-213'));
});

test('cobrar una orden nacida de una cotizacion conserva su placa y destino', function () {
    $user = vendedorUser();
    $client = Client::factory()->create();
    $vehicle = Vehicle::factory()->create(['client_id' => $client->id, 'placa' => 'AVR-213']);
    $quote = Quote::factory()->create(['client_id' => $client->id, 'vehicle_id' => $vehicle->id, 'referencia' => 'PLACA: AVR-213']);
    $order = ServiceOrder::factory()->create(['client_id' => $client->id, 'quote_id' => $quote->id, 'vehicle_id' => $vehicle->id]);

    $this->actingAs($user)->get(route('vendedor.ventas.create', [$user->currentTeam, 'orden_servicio' => $order->id]))
        ->assertInertia(fn ($page) => $page->where('venta.destino', 'vehiculo')->where('venta.vehicle_id', $vehicle->id)->where('venta.referencia', 'PLACA: AVR-213'));
});

test('corregir la placa cotizada evita heredar el vehiculo anterior de la orden', function () {
    $user = vendedorUser();
    $client = Client::factory()->create();
    $vehicle = Vehicle::factory()->create(['client_id' => $client->id, 'placa' => 'AVR-213']);
    $quote = Quote::factory()->create(['client_id' => $client->id, 'vehicle_id' => $vehicle->id, 'referencia' => 'PLACA: AVR-213']);
    $order = ServiceOrder::factory()->create(['client_id' => $client->id, 'quote_id' => $quote->id, 'vehicle_id' => $vehicle->id]);

    $this->actingAs($user)->put(route('vendedor.cotizaciones.update', [$user->currentTeam, $quote]), [
        ...datosCotizacionDestino($client), 'destino' => 'vehiculo', 'vehicle_id' => null, 'referencia' => 'PLACA: BBB-222',
    ])->assertSessionHasNoErrors();

    $this->get(route('vendedor.ventas.create', [$user->currentTeam, 'orden_servicio' => $order->id]))
        ->assertInertia(fn ($page) => $page->where('venta.destino', 'vehiculo')->where('venta.vehicle_id', null)->where('venta.referencia', 'PLACA: BBB-222'));
});
