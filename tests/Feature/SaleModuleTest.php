<?php

use App\Actions\Sales\CreateSale;
use App\Models\Client;
use App\Models\Equipment;
use App\Models\Installment;
use App\Models\InventoryMovement;
use App\Models\InventoryUnit;
use App\Models\Product;
use App\Models\Quote;
use App\Models\QuoteItem;
use App\Models\Sale;
use App\Models\Sede;
use App\Models\Service;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('vender una unidad nueva descuenta stock, crea kardex, equipo y calcula totales', function () {
    $user = vendedorUser();
    $client = Client::factory()->create();
    $sede = Sede::factory()->almacen()->create();
    $product = Product::factory()->create(['precio_venta' => 100]);
    $unit = InventoryUnit::factory()->create([
        'product_id' => $product->id,
        'sede_almacen_id' => $sede->id,
        'numero_serie' => 'BF-SERIE-001',
        'estado' => 'disponible',
    ]);

    $sale = app(CreateSale::class)->handle([
        'client_id' => $client->id,
        'sede_id' => $sede->id,
        'fecha' => now()->toDateString(),
        'destino' => 'local_cliente',
        'condicion_pago' => 'contado',
        'comprobante_tipo' => 'factura',
    ], [
        [
            'tipo_linea' => 'unidad_nueva',
            'numero_serie' => $unit->numero_serie,
            'product_id' => $product->id,
            'cantidad' => 2,
            'precio_unitario' => 100,
            'descuento' => 10,
        ],
    ], $user->id);

    // Los precios incluyen IGV: 2 x 100 - 10 = S/ 190 que paga el cliente.
    expect((float) $sale->subtotal)->toEqual(161.02)
        ->and((float) $sale->igv)->toEqual(28.98)
        ->and((float) $sale->total)->toEqual(190.0)
        ->and($unit->refresh()->estado)->toBe('vendido')
        ->and(InventoryMovement::where('referencia_type', $sale->getMorphClass())->where('referencia_id', $sale->id)->count())->toBe(1)
        ->and(Equipment::where('client_id', $client->id)->where('numero_serie', 'BF-SERIE-001')->exists())->toBeTrue();
});

test('vender la misma unidad dos veces lanza validation exception', function () {
    $user = vendedorUser();
    $client = Client::factory()->create();
    $sede = Sede::factory()->almacen()->create();
    $product = Product::factory()->create(['precio_venta' => 100]);
    $unit = InventoryUnit::factory()->create([
        'product_id' => $product->id,
        'sede_almacen_id' => $sede->id,
        'numero_serie' => 'BF-SERIE-002',
        'estado' => 'disponible',
    ]);
    $payload = [
        'client_id' => $client->id,
        'sede_id' => $sede->id,
        'fecha' => now()->toDateString(),
        'destino' => 'local_cliente',
        'condicion_pago' => 'contado',
        'comprobante_tipo' => 'factura',
    ];
    $items = [[
        'tipo_linea' => 'unidad_nueva',
        'numero_serie' => $unit->numero_serie,
        'product_id' => $product->id,
        'cantidad' => 1,
        'precio_unitario' => 100,
    ]];

    app(CreateSale::class)->handle($payload, $items, $user->id);

    expect(fn () => app(CreateSale::class)->handle($payload, $items, $user->id))
        ->toThrow(ValidationException::class);
});

test('recarga de servicio no descuenta stock y requiere equipo del cliente correcto', function () {
    $user = vendedorUser();
    $client = Client::factory()->create();
    $otherClient = Client::factory()->create();
    $sede = Sede::factory()->almacen()->create();
    $service = Service::factory()->create(['precio_venta' => 80]);
    $product = Product::factory()->create();
    $unit = InventoryUnit::factory()->create([
        'product_id' => $product->id,
        'sede_almacen_id' => $sede->id,
        'estado' => 'disponible',
    ]);
    $equipment = Equipment::factory()->create([
        'client_id' => $client->id,
        'product_id' => $product->id,
        'numero_serie' => 'EQ-CLIENTE-001',
    ]);
    Equipment::factory()->create([
        'client_id' => $otherClient->id,
        'product_id' => $product->id,
        'numero_serie' => 'EQ-OTRO-001',
    ]);
    $fechaAntes = $equipment->proxima_fecha_atencion?->toDateString();

    $sale = app(CreateSale::class)->handle([
        'client_id' => $client->id,
        'sede_id' => $sede->id,
        'fecha' => now()->toDateString(),
        'destino' => 'local_cliente',
        'condicion_pago' => 'contado',
        'comprobante_tipo' => 'factura',
    ], [[
        'tipo_linea' => 'recarga_servicio',
        'numero_serie' => $equipment->numero_serie,
        'service_id' => $service->id,
        'cantidad' => 1,
        'precio_unitario' => 80,
    ]], $user->id);

    expect($unit->refresh()->estado)->toBe('disponible')
        ->and($sale->items()->first()->equipment_id)->toBe($equipment->id)
        ->and(InventoryMovement::count())->toBe(0)
        // V5: guardar la venta no renueva la fecha; la renueva el cierre del trabajo.
        ->and($equipment->refresh()->proxima_fecha_atencion?->toDateString())->toBe($fechaAntes);

    expect(fn () => app(CreateSale::class)->handle([
        'client_id' => $client->id,
        'sede_id' => $sede->id,
        'fecha' => now()->toDateString(),
        'destino' => 'local_cliente',
        'condicion_pago' => 'contado',
        'comprobante_tipo' => 'factura',
    ], [[
        'tipo_linea' => 'recarga_servicio',
        'numero_serie' => 'EQ-OTRO-001',
        'service_id' => $service->id,
        'cantidad' => 1,
        'precio_unitario' => 80,
    ]], $user->id))->toThrow(ValidationException::class);
});

test('venta a credito genera una cuota con monto total y vencimiento a treinta dias', function () {
    $user = vendedorUser();
    $client = Client::factory()->create();
    $sede = Sede::factory()->almacen()->create();
    $product = Product::factory()->create(['precio_venta' => 100]);
    $unit = InventoryUnit::factory()->create([
        'product_id' => $product->id,
        'sede_almacen_id' => $sede->id,
        'numero_serie' => 'BF-SERIE-003',
        'estado' => 'disponible',
    ]);
    $fecha = now()->startOfDay();

    $sale = app(CreateSale::class)->handle([
        'client_id' => $client->id,
        'sede_id' => $sede->id,
        'fecha' => $fecha->toDateString(),
        'destino' => 'local_cliente',
        'condicion_pago' => 'credito_30',
        'comprobante_tipo' => 'factura',
    ], [[
        'tipo_linea' => 'unidad_nueva',
        'numero_serie' => $unit->numero_serie,
        'product_id' => $product->id,
        'cantidad' => 1,
        'precio_unitario' => 100,
    ]], $user->id);

    $installment = Installment::where('sale_id', $sale->id)->firstOrFail();

    expect($sale->installments)->toHaveCount(1)
        ->and((float) $installment->monto)->toEqual((float) $sale->total)
        ->and($installment->fecha_vencimiento->toDateString())->toBe($fecha->copy()->addDays(30)->toDateString());
});

function venderUnidadDesdeCotizacion(int $clientId, Quote $quote): Sale
{
    $sede = Sede::factory()->almacen()->create();
    $product = Product::factory()->create(['precio_venta' => 100]);
    $unit = InventoryUnit::factory()->create([
        'product_id' => $product->id,
        'sede_almacen_id' => $sede->id,
        'numero_serie' => 'BF-COT-'.$quote->id,
        'estado' => 'disponible',
    ]);

    return app(CreateSale::class)->handle([
        'client_id' => $clientId,
        'sede_id' => $sede->id,
        'quote_id' => $quote->id,
        'fecha' => now()->toDateString(),
        'destino' => 'local_cliente',
        'condicion_pago' => 'contado',
        'comprobante_tipo' => 'factura',
    ], [[
        'tipo_linea' => 'unidad_nueva',
        'numero_serie' => $unit->numero_serie,
        'product_id' => $product->id,
        'cantidad' => 1,
        'precio_unitario' => 100,
    ]], vendedorUser()->id);
}

test('crear una venta desde una cotizacion aceptada la marca como convertida', function () {
    $client = Client::factory()->create();
    $quote = Quote::factory()->aceptada()->create(['client_id' => $client->id]);

    $sale = venderUnidadDesdeCotizacion($client->id, $quote);

    expect($quote->refresh()->estado)->toBe('convertida')
        ->and($sale->quote_id)->toBe($quote->id);
});

test('una cotizacion vencida no se puede pasar a venta', function () {
    $client = Client::factory()->create();
    $quote = Quote::factory()->vencida()->create(['client_id' => $client->id]);

    expect(fn () => venderUnidadDesdeCotizacion($client->id, $quote))
        ->toThrow(ValidationException::class, "La cotización {$quote->numero} no se puede pasar a venta porque está vencida.");

    expect($quote->refresh()->estado)->toBe('vencida')
        ->and(Sale::count())->toBe(0);
});

test('la cotizacion de otro cliente no se puede pasar a venta', function () {
    $quote = Quote::factory()->aceptada()->create();
    $otroCliente = Client::factory()->create();

    expect(fn () => venderUnidadDesdeCotizacion($otroCliente->id, $quote))
        ->toThrow(ValidationException::class, "La cotización {$quote->numero} pertenece a otro cliente.");

    expect($quote->refresh()->estado)->toBe('aceptada');
});

test('la nueva venta desde una cotizacion aceptada precarga el cliente y los items cotizados', function () {
    $user = vendedorUser();
    $client = Client::factory()->create();
    $quote = Quote::factory()->aceptada()->create(['client_id' => $client->id]);
    QuoteItem::factory()->create(['quote_id' => $quote->id, 'cantidad' => 2, 'precio_unitario' => 150]);

    $this->actingAs($user)
        ->get(route('vendedor.ventas.create', ['current_team' => $user->currentTeam, 'cotizacion' => $quote->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('vendedor/ventas/nueva')
            ->where('quote.numero', $quote->numero)
            ->where('quote.client.id', $client->id)
            ->has('quote.items', 1)
            ->where('quote.items.0.cantidad', 2)
            ->where('quote.items.0.precio_unitario', 150)
        );
});

test('una cotizacion vencida no se precarga en la nueva venta', function () {
    $user = vendedorUser();
    $quote = Quote::factory()->vencida()->create();

    $this->actingAs($user)
        ->get(route('vendedor.ventas.create', ['current_team' => $user->currentTeam, 'cotizacion' => $quote->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('quote', null));
});

test('el detalle de la venta muestra la serie de cada unidad y la fecha sin hora', function () {
    $user = vendedorUser();
    $sede = Sede::factory()->almacen()->create();
    $product = Product::factory()->create();
    $unit = InventoryUnit::factory()->create([
        'product_id' => $product->id,
        'sede_almacen_id' => $sede->id,
        'numero_serie' => 'BF-EQ-000077',
        'capacidad' => '6 kg',
        'serie_fabricante' => 'FAB-123',
        'marca' => 'BADGER',
        'estado' => 'disponible',
    ]);

    $sale = app(CreateSale::class)->handle([
        'client_id' => Client::factory()->create()->id,
        'sede_id' => $sede->id,
        'fecha' => '2026-09-25',
        'destino' => 'local_cliente',
        'condicion_pago' => 'contado',
        'comprobante_tipo' => 'factura',
    ], [
        [
            'tipo_linea' => 'unidad_nueva',
            'numero_serie' => $unit->numero_serie,
            'product_id' => $product->id,
            'cantidad' => 1,
            'precio_unitario' => 65,
        ],
    ], $user->id);

    expect(Equipment::where('numero_serie', 'BF-EQ-000077')->first())
        ->capacidad->toBe('6 kg')
        ->serie_fabricante->toBe('FAB-123')
        ->marca->toBe('BADGER');

    $this->actingAs($user)
        ->get(route('vendedor.ventas.show', ['current_team' => $user->currentTeam, 'sale' => $sale]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('sale.fecha', '2026-09-25')
            ->where('sale.items.0.numero_serie', 'BF-EQ-000077')
        );
});

function ventaConUnaUnidad(Client $client, string $comprobante, float $precio): Sale
{
    $sede = Sede::factory()->almacen()->create();
    $product = Product::factory()->create();
    $unit = InventoryUnit::factory()->create([
        'product_id' => $product->id,
        'sede_almacen_id' => $sede->id,
        'estado' => 'disponible',
    ]);

    return app(CreateSale::class)->handle([
        'client_id' => $client->id,
        'sede_id' => $sede->id,
        'fecha' => now()->toDateString(),
        'destino' => 'local_cliente',
        'condicion_pago' => 'contado',
        'comprobante_tipo' => $comprobante,
    ], [
        [
            'tipo_linea' => 'unidad_nueva',
            'numero_serie' => $unit->numero_serie,
            'product_id' => $product->id,
            'cantidad' => 1,
            'precio_unitario' => $precio,
        ],
    ], vendedorUser()->id);
}

test('no se puede emitir factura a un cliente con dni ni a clientes varios', function (Client $cliente) {
    expect(fn () => ventaConUnaUnidad($cliente, 'factura', 65))
        ->toThrow(ValidationException::class, 'La factura solo se emite a clientes con RUC');

    expect(Sale::count())->toBe(0);
})->with([
    'dni' => fn () => Client::factory()->dni()->create(),
    'clientes varios' => fn () => Client::clientesVarios(),
]);

test('un cliente con dni si puede recibir boleta', function () {
    $sale = ventaConUnaUnidad(Client::factory()->dni()->create(), 'boleta', 65);

    expect($sale->comprobante_tipo)->toBe('boleta');
});

test('la boleta a clientes varios se permite hasta el limite y se rechaza al superarlo', function () {
    $sale = ventaConUnaUnidad(Client::clientesVarios(), 'boleta', 500);

    expect((float) $sale->total)->toEqual(500.0);

    expect(fn () => ventaConUnaUnidad(Client::clientesVarios(), 'boleta', 750))
        ->toThrow(ValidationException::class, 'no puede superar S/ 700.00');
});

test('clientes varios es un unico cliente reutilizable', function () {
    expect(Client::clientesVarios()->id)->toBe(Client::clientesVarios()->id)
        ->and(Client::clientesVarios()->razon_social)->toBe('CLIENTES VARIOS');
});
