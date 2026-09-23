<?php

use App\Actions\Sales\CreateSale;
use App\Models\Client;
use App\Models\Equipment;
use App\Models\Installment;
use App\Models\InventoryMovement;
use App\Models\InventoryUnit;
use App\Models\Product;
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

    expect((float) $sale->subtotal)->toEqual(190.0)
        ->and((float) $sale->igv)->toEqual(34.2)
        ->and((float) $sale->total)->toEqual(224.2)
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
        ->and(InventoryMovement::count())->toBe(0);

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
