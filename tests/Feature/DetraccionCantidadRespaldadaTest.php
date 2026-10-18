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
 * Regresiones de la auditoría independiente (H-1):
 *
 * "un consumo de una unidad no acredita diez unidades como parte del servicio"
 * "consumo instalado no acredita otra línea del mismo producto vendida suelta"
 *
 * La regla exige vínculo verificable y cantidades que no superen las realmente
 * respaldadas: un consumo no se reutiliza para justificar varias líneas, y que
 * coincida el product_id no basta.
 */
function ventaConUnConsumo(float $total = 800, int $cantidadConsumida = 1): array
{
    $sale = Sale::factory()->create(['comprobante_tipo' => 'factura', 'estado' => 'borrador', 'total' => $total]);
    $service = Service::factory()->create(['codigo_detraccion' => '022']);
    SaleItem::factory()->forService($service)->create(['sale_id' => $sale->id]);

    $order = ServiceOrder::factory()->create(['sale_id' => $sale->id]);
    $deficiency = Deficiency::factory()->create(['service_order_id' => $order->id]);
    $product = Product::factory()->create(['serializado' => false]);

    InventoryMovement::create([
        'tipo' => 'salida_servicio',
        'referencia_type' => Deficiency::class,
        'referencia_id' => $deficiency->id,
        'product_id' => $product->id,
        'sede_id' => Sede::factory()->create()->id,
        'cantidad' => -1 * $cantidadConsumida,
    ]);

    return [$sale, $product];
}

function lineaSuelta(Sale $sale, Product $product, int $cantidad): void
{
    SaleItem::factory()->create([
        'sale_id' => $sale->id,
        'product_id' => $product->id,
        'service_id' => null,
        'tipo_linea' => 'producto',
        'cantidad' => $cantidad,
        'deficiency_id' => null,
    ]);
}

test('un consumo de una unidad no acredita diez unidades como parte del servicio', function () {
    [$sale, $product] = ventaConUnConsumo();
    lineaSuelta($sale, $product, 10);

    expect(fn () => app(DetraccionCalculator::class)->paraVenta($sale))
        ->toThrow(ValidationException::class);
});

test('consumo instalado no acredita otra linea del mismo producto vendida suelta', function () {
    [$sale, $product] = ventaConUnConsumo();
    lineaSuelta($sale, $product, 1);
    lineaSuelta($sale, $product, 3);

    expect(fn () => app(DetraccionCalculator::class)->paraVenta($sale))
        ->toThrow(ValidationException::class);
});

test('el consumo exacto si acredita la linea del mismo producto', function () {
    [$sale, $product] = ventaConUnConsumo();
    lineaSuelta($sale, $product, 1);

    $resultado = app(DetraccionCalculator::class)->paraVenta($sale);

    expect($resultado)->toBe(['aplica' => true, 'monto' => 96.0, 'codigo_bien' => '022', 'porcentaje' => 12.0]);
});

test('un consumo no se reutiliza para justificar dos lineas identicas', function () {
    [$sale, $product] = ventaConUnConsumo(800, 1);
    lineaSuelta($sale, $product, 1);
    lineaSuelta($sale, $product, 1);

    expect(fn () => app(DetraccionCalculator::class)->paraVenta($sale))
        ->toThrow(ValidationException::class);
});

test('un consumo de otra venta no acredita esta operacion', function () {
    [$sale, $product] = ventaConUnConsumo();

    // Segunda venta con su propia orden y su propio consumo del mismo producto.
    $otra = Sale::factory()->create(['comprobante_tipo' => 'factura', 'estado' => 'borrador', 'total' => 800]);
    SaleItem::factory()->forService(Service::factory()->create(['codigo_detraccion' => '022']))->create(['sale_id' => $otra->id]);
    $otraOrden = ServiceOrder::factory()->create(['sale_id' => $otra->id]);
    $otraDeficiencia = Deficiency::factory()->create(['service_order_id' => $otraOrden->id]);
    InventoryMovement::create([
        'tipo' => 'salida_servicio',
        'referencia_type' => Deficiency::class,
        'referencia_id' => $otraDeficiencia->id,
        'product_id' => $product->id,
        'sede_id' => Sede::factory()->create()->id,
        'cantidad' => -1,
    ]);

    // La venta original NO tiene consumo propio: no puede acreditarse.
    $propia = Sale::factory()->create(['comprobante_tipo' => 'factura', 'estado' => 'borrador', 'total' => 800]);
    SaleItem::factory()->forService(Service::factory()->create(['codigo_detraccion' => '022']))->create(['sale_id' => $propia->id]);
    lineaSuelta($propia, $product, 1);

    expect(fn () => app(DetraccionCalculator::class)->paraVenta($propia))
        ->toThrow(ValidationException::class);
});

test('coincidir en product_id no basta sin consumo de esta venta', function () {
    $sale = Sale::factory()->create(['comprobante_tipo' => 'factura', 'estado' => 'borrador', 'total' => 800]);
    SaleItem::factory()->forService(Service::factory()->create(['codigo_detraccion' => '022']))->create(['sale_id' => $sale->id]);
    $product = Product::factory()->create(['serializado' => false]);
    lineaSuelta($sale, $product, 1);

    expect(fn () => app(DetraccionCalculator::class)->paraVenta($sale))
        ->toThrow(ValidationException::class);
});

test('los adicionales cotizados y autorizados siguen funcionando aunque excedan el consumo', function () {
    [$sale, $product] = ventaConUnConsumo(800, 1);
    $deficiency = Deficiency::factory()->create();

    SaleItem::factory()->create([
        'sale_id' => $sale->id,
        'product_id' => $product->id,
        'service_id' => null,
        'tipo_linea' => 'producto',
        'cantidad' => 5,
        'deficiency_id' => $deficiency->id,
    ]);

    $resultado = app(DetraccionCalculator::class)->paraVenta($sale);

    expect($resultado)->toBe(['aplica' => true, 'monto' => 96.0, 'codigo_bien' => '022', 'porcentaje' => 12.0]);
});

test('la relacion ambigua da un mensaje claro y no presume que todo esta instalado', function () {
    [$sale, $product] = ventaConUnConsumo(800, 1);
    lineaSuelta($sale, $product, 10);

    expect(fn () => app(DetraccionCalculator::class)->paraVenta($sale))
        ->toThrow(ValidationException::class, 'factura aparte');
});

test('la correccion fiscal no crea movimientos de stock ni duplica cobros', function () {
    [$sale, $product] = ventaConUnConsumo(800, 1);
    lineaSuelta($sale, $product, 1);

    $movimientosAntes = InventoryMovement::count();

    app(DetraccionCalculator::class)->paraVenta($sale);

    expect(InventoryMovement::count())->toBe($movimientosAntes);
    expect($sale->items()->count())->toBe(2);
});
