<?php

use App\Models\Deficiency;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Sede;
use App\Models\Service;
use App\Models\ServiceOrder;
use App\Services\Billing\DetraccionCalculator;
use Illuminate\Validation\ValidationException;

/**
 * Regla nueva (confirmada por el dueño con su contador):
 *
 *  1. El repuesto o producto que se usa o instala DENTRO del servicio es parte
 *     del servicio: va en la MISMA factura y la detracción se calcula sobre el
 *     TOTAL con el código del servicio. Son parte del servicio:
 *       - la línea con sale_items.deficiency_id (adicional detectado en una orden)
 *       - el repuesto consumido en una orden de servicio de esa misma venta
 *  2. El producto SUELTO que no se instala (extintor nuevo, accesorio vendido
 *     aparte) NO puede ir en la misma factura que un servicio sujeto a
 *     detracción: hay que emitir factura aparte.
 *  3. Servicios con códigos distintos, o un servicio sin clasificar, siguen
 *     bloqueados como antes.
 *  4. No cambia: boleta y nota de venta nunca llevan detracción; hasta S/700
 *     no hay detracción; la factura emitida conserva su snapshot.
 */
function facturaServicioConProducto(float $total, array $extra = []): Sale
{
    $sale = Sale::factory()->create(array_merge(['comprobante_tipo' => 'factura', 'total' => $total], $extra));
    SaleItem::factory()->forService(
        Service::factory()->create(['codigo_detraccion' => '022'])
    )->create(['sale_id' => $sale->id]);

    return $sale;
}

function lineaProductoSuelto(Sale $sale, array $attrs = []): SaleItem
{
    $product = $attrs['product_id'] ?? null;
    unset($attrs['product_id']);

    return SaleItem::factory()->create(array_merge([
        'sale_id' => $sale->id,
        'product_id' => $product ?? Product::factory()->create()->id,
        'service_id' => null,
        'tipo_linea' => 'unidad_nueva',
    ], $attrs));
}

test('el adicional autorizado con deficiency_id va en la misma factura y la detraccion se calcula sobre el total', function () {
    $sale = facturaServicioConProducto(800);

    // Repuesto detectado por el técnico y autorizado por el cliente (deficiency_id).
    $deficiency = Deficiency::factory()->create();
    lineaProductoSuelto($sale, ['deficiency_id' => $deficiency->id]);

    $resultado = app(DetraccionCalculator::class)->paraVenta($sale);

    expect($resultado)->toBe(['aplica' => true, 'monto' => 96.0, 'codigo_bien' => '022', 'porcentaje' => 12.0]);
});

test('el repuesto consumido en una orden de esa misma venta va en la misma factura', function () {
    $sale = facturaServicioConProducto(800);

    $order = ServiceOrder::factory()->create(['sale_id' => $sale->id]);
    $deficiency = Deficiency::factory()->create(['service_order_id' => $order->id]);
    $repuesto = Product::factory()->create();
    InventoryMovement::create([
        'tipo' => 'salida_servicio',
        'referencia_type' => Deficiency::class,
        'referencia_id' => $deficiency->id,
        'product_id' => $repuesto->id,
        'sede_id' => Sede::factory()->create()->id,
        'cantidad' => -1,
    ]);
    lineaProductoSuelto($sale, ['product_id' => $repuesto->id, 'cantidad' => 1]);

    $resultado = app(DetraccionCalculator::class)->paraVenta($sale);

    expect($resultado)->toBe(['aplica' => true, 'monto' => 96.0, 'codigo_bien' => '022', 'porcentaje' => 12.0]);
});

test('producto suelto que no se instala pide factura aparte cuando hay detraccion', function () {
    $sale = facturaServicioConProducto(800);
    lineaProductoSuelto($sale);

    expect(fn () => app(DetraccionCalculator::class)->paraVenta($sale))
        ->toThrow(ValidationException::class, 'factura aparte');
});

test('producto suelto sin detraccion por minimo no se bloquea', function () {
    $sale = facturaServicioConProducto(600);
    lineaProductoSuelto($sale);

    $resultado = app(DetraccionCalculator::class)->paraVenta($sale);

    expect($resultado['aplica'])->toBeFalse();
});

test('la boleta con servicio y producto no lleva detraccion y no se bloquea', function () {
    $sale = facturaServicioConProducto(900, ['comprobante_tipo' => 'boleta']);
    lineaProductoSuelto($sale);

    $resultado = app(DetraccionCalculator::class)->paraVenta($sale);

    expect($resultado['aplica'])->toBeFalse();
});

test('mezclar servicios con codigos distintos sigue bloqueado', function () {
    $sale = facturaServicioConProducto(800);
    SaleItem::factory()->forService(
        Service::factory()->create(['codigo_detraccion' => '020'])
    )->create(['sale_id' => $sale->id]);

    expect(fn () => app(DetraccionCalculator::class)->paraVenta($sale))
        ->toThrow(ValidationException::class, 'distintos');
});

test('un producto suelto junto a dos servicios con codigos distintos sigue bloqueado por los codigos', function () {
    $sale = facturaServicioConProducto(800);
    SaleItem::factory()->forService(
        Service::factory()->create(['codigo_detraccion' => '020'])
    )->create(['sale_id' => $sale->id]);
    lineaProductoSuelto($sale);

    expect(fn () => app(DetraccionCalculator::class)->paraVenta($sale))
        ->toThrow(ValidationException::class);
});

test('la factura emitida conserva su snapshot aunque cambie el codigo del servicio', function () {
    $service = Service::factory()->create(['codigo_detraccion' => '022']);
    $sale = Sale::factory()->create([
        'comprobante_tipo' => 'factura',
        'total' => 800,
        'estado' => 'confirmada',
        'codigo_detraccion_snapshot' => '022',
        'monto_detraccion_snapshot' => 96.0,
    ]);
    SaleItem::factory()->forService($service)->create(['sale_id' => $sale->id]);

    $service->update(['codigo_detraccion' => '020']);

    $resultado = app(DetraccionCalculator::class)->paraVenta($sale);

    expect($resultado)->toBe(['aplica' => true, 'monto' => 96.0, 'codigo_bien' => '022', 'porcentaje' => 12.0]);
});

test('la nota de venta nunca lleva detraccion aunque mezcle servicio y producto', function () {
    $sale = facturaServicioConProducto(900, ['comprobante_tipo' => 'nota_venta']);
    lineaProductoSuelto($sale);

    $resultado = app(DetraccionCalculator::class)->paraVenta($sale);

    expect($resultado['aplica'])->toBeFalse();
});
