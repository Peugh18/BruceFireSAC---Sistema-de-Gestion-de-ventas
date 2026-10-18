<?php

use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Service;
use App\Services\Billing\DetraccionCalculator;
use Illuminate\Validation\ValidationException;

function facturaClasificada(string $codigo, float $total = 1180): Sale
{
    $sale = Sale::factory()->create(['comprobante_tipo' => 'factura', 'total' => $total]);
    SaleItem::factory()->forService(Service::factory()->create(['codigo_detraccion' => $codigo]))->create(['sale_id' => $sale->id]);

    return $sale;
}

test('factura usa la clasificacion especifica del servicio y no el codigo global', function (string $codigo) {
    config(['billing.detraccion.codigo_bien' => '020']);
    $resultado = app(DetraccionCalculator::class)->paraVenta(facturaClasificada($codigo));
    expect($resultado)->toBe(['aplica' => true, 'monto' => 141.6, 'codigo_bien' => $codigo, 'porcentaje' => 12.0]);
})->with(['022', '020', '037']);

test('solo supera setecientos y las boletas quedan exceptuadas', function () {
    $sale = facturaClasificada('022', 700);
    expect(app(DetraccionCalculator::class)->paraVenta($sale)['aplica'])->toBeFalse();
    $sale->total = 700.01;
    expect(app(DetraccionCalculator::class)->paraVenta($sale)['aplica'])->toBeTrue();
    $sale->comprobante_tipo = 'boleta';
    expect(app(DetraccionCalculator::class)->paraVenta($sale)['aplica'])->toBeFalse();
});

test('mezcla de codigos no elige un codigo arbitrario', function () {
    $sale = facturaClasificada('022');
    SaleItem::factory()->forService(Service::factory()->create(['codigo_detraccion' => '020']))->create(['sale_id' => $sale->id]);
    expect(fn () => app(DetraccionCalculator::class)->paraVenta($sale))->toThrow(ValidationException::class, 'distintos');
});

test('servicio combinado sin clasificar requiere revision en el catalogo', function () {
    $sale = Sale::factory()->create(['comprobante_tipo' => 'factura', 'total' => 1180]);
    SaleItem::factory()->forService(Service::factory()->create(['nombre' => 'Recarga y mantenimiento PQS', 'codigo_detraccion' => null]))->create(['sale_id' => $sale->id]);
    expect(fn () => app(DetraccionCalculator::class)->paraVenta($sale))->toThrow(ValidationException::class, 'clasificación');
});

test('servicios no gravados no llevan detraccion aunque superen el minimo', function () {
    $sale = facturaClasificada('022');
    $sale->items()->update(['tipo_afectacion_igv' => '20']);
    expect(app(DetraccionCalculator::class)->paraVenta($sale)['aplica'])->toBeFalse();
});

test('categorias inequivocas tienen codigo y paquete combinado requiere eleccion explicita', function () {
    $recarga = new Service(['nombre' => 'Recarga PQS 6 kg', 'categoria' => 'recarga']);
    $mantenimiento = new Service(['nombre' => 'Mantenimiento extintor', 'categoria' => 'mantenimiento']);
    $combinado = new Service(['nombre' => 'Recarga y mantenimiento PQS', 'categoria' => 'recarga']);

    expect($recarga->codigoDetraccion())->toBe('022');
    expect($mantenimiento->codigoDetraccion())->toBe('020');
    expect($combinado->codigoDetraccion())->toBeNull();
    $combinado->codigo_detraccion = '022';
    expect($combinado->codigoDetraccion())->toBe('022');
});
