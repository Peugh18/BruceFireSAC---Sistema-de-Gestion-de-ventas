<?php

use App\Actions\Billing\IssueCreditNote;
use App\Actions\Billing\IssueDebitNote;
use App\Actions\Sales\CreateSale;
use App\Models\Client;
use App\Models\ElectronicDocument;
use App\Models\InventoryMovement;
use App\Models\InventoryUnit;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SalePayment;
use App\Models\Sede;
use App\Services\Billing\GreenterService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

/**
 * Venta confirmada de una unidad nueva con su factura aceptada por SUNAT.
 *
 * @return array{0: Sale, 1: ElectronicDocument, 2: InventoryUnit}
 */
function ventaConFactura(string $condicionPago = 'contado', string $tipo = 'factura'): array
{
    $sede = Sede::factory()->almacen()->create();
    $product = Product::factory()->create();
    $unit = InventoryUnit::factory()->create([
        'product_id' => $product->id,
        'sede_almacen_id' => $sede->id,
        'estado' => 'disponible',
    ]);

    $sale = app(CreateSale::class)->handle([
        'client_id' => Client::factory()->create()->id,
        'sede_id' => $sede->id,
        'fecha' => now()->toDateString(),
        'destino' => 'local_cliente',
        'condicion_pago' => $condicionPago,
        'comprobante_tipo' => $tipo,
    ], [[
        'tipo_linea' => 'unidad_nueva',
        'numero_serie' => $unit->numero_serie,
        'product_id' => $product->id,
        'cantidad' => 1,
        'precio_unitario' => 100,
    ]], vendedorUser()->id);

    $sale->update(['estado' => 'confirmada']);
    $documento = ElectronicDocument::factory()->create([
        'sale_id' => $sale->id,
        'tipo' => $tipo,
        'serie' => $tipo === 'factura' ? 'F001' : 'B001',
        'sunat_estado' => 'aceptado',
    ]);

    return [$sale->refresh(), $documento, $unit];
}

test('una nota de crédito de anulación total devuelve stock kardex y anula la venta', function () {
    [$sale, $factura, $unit] = ventaConFactura('credito_30');
    SalePayment::factory()->create(['sale_id' => $sale->id, 'monto' => 10]);

    $nota = app(IssueCreditNote::class)->handle($factura, '01', 'Anulación', (float) $sale->total);

    expect($nota->serie)->toBe('FC01')
        ->and($nota->importe)->toBe(number_format((float) $sale->total, 2, '.', ''))
        ->and($sale->refresh()->estado)->toBe('anulada')
        ->and($unit->refresh()->estado)->toBe('disponible')
        ->and(InventoryMovement::where('tipo', 'ingreso')->where('referencia_id', $sale->id)->count())->toBe(1)
        ->and($sale->installments()->count())->toBe(0)
        ->and($sale->payments()->count())->toBe(1);
});

test('una nota de crédito parcial no anula la venta ni toca el stock', function () {
    [$sale, $factura, $unit] = ventaConFactura();

    app(IssueCreditNote::class)->handle($factura, '04', 'Descuento global', 20);

    expect($sale->refresh()->estado)->toBe('confirmada')
        ->and($unit->refresh()->estado)->toBe('vendido');
});

test('las notas de crédito acumuladas no pueden superar el total del comprobante', function () {
    [$sale, $factura] = ventaConFactura();
    $total = (float) $sale->total;

    app(IssueCreditNote::class)->handle($factura, '04', 'Descuento', $total - 10);

    expect(fn () => app(IssueCreditNote::class)->handle($factura, '04', 'Otro descuento', 20))
        ->toThrow(ValidationException::class);
});

test('una venta anulada rechaza nuevas notas de crédito y de débito', function () {
    [$sale, $factura] = ventaConFactura();
    app(IssueCreditNote::class)->handle($factura, '01', 'Anulación', (float) $sale->total);

    expect(fn () => app(IssueCreditNote::class)->handle($factura, '04', 'Descuento', 1))
        ->toThrow(ValidationException::class)
        ->and(fn () => app(IssueDebitNote::class)->handle($factura, '01', 'Mora', 5))
        ->toThrow(ValidationException::class);
});

test('la nota de débito usa serie según el comprobante y guarda su importe', function () {
    [, $factura] = ventaConFactura();
    [, $boleta] = ventaConFactura(tipo: 'boleta');

    $ndFactura = app(IssueDebitNote::class)->handle($factura, '01', 'Intereses por mora', 25);
    $ndBoleta = app(IssueDebitNote::class)->handle($boleta, '02', 'Aumento en el valor', 10);

    expect($ndFactura->tipo)->toBe('nota_debito')
        ->and($ndFactura->serie)->toBe('FD01')
        ->and($ndFactura->importe)->toBe('25.00')
        ->and($ndFactura->cpe_afectado_id)->toBe($factura->id)
        ->and($ndBoleta->serie)->toBe('BD01');
});

test('la nota de débito no modifica stock ni estado de la venta', function () {
    [$sale, $factura, $unit] = ventaConFactura();

    app(IssueDebitNote::class)->handle($factura, '01', 'Mora', 30);

    expect($sale->refresh()->estado)->toBe('confirmada')
        ->and($unit->refresh()->estado)->toBe('vendido');
});

test('greenter construye las notas con el importe persistido y el comprobante afectado', function () {
    [, $factura] = ventaConFactura();
    $credito = app(IssueCreditNote::class)->handle($factura, '04', 'Descuento', 59);
    $debito = app(IssueDebitNote::class)->handle($factura, '01', 'Mora', 118);

    $noteCredito = app(GreenterService::class)->buildNote($credito->refresh());
    $noteDebito = app(GreenterService::class)->buildNote($debito->refresh());

    expect($noteCredito->getTipoDoc())->toBe('07')
        ->and($noteCredito->getTipDocAfectado())->toBe('01')
        ->and($noteCredito->getNumDocfectado())->toBe("{$factura->serie}-{$factura->correlativo}")
        ->and((float) $noteCredito->getMtoImpVenta())->toBe(59.0)
        ->and((float) $noteCredito->getMtoIGV())->toBe(9.0)
        ->and($noteDebito->getTipoDoc())->toBe('08')
        ->and((float) $noteDebito->getMtoOperGravadas())->toBe(100.0);
});

test('el vendedor emite notas desde el detalle y si SUNAT no responde quedan pendientes', function () {
    [, $factura] = ventaConFactura();
    $vendedor = vendedorUser();
    $team = ['current_team' => $vendedor->currentTeam];

    $this->actingAs($vendedor)
        ->post(route('vendedor.notas-credito.store', $team), [
            'electronic_document_id' => $factura->id,
            'motivo_catalogo' => '04',
            'detalle' => 'Descuento global',
            'importe' => 10,
        ])
        ->assertSessionHas('error');

    $this->actingAs($vendedor)
        ->post(route('vendedor.notas-debito.store', $team), [
            'electronic_document_id' => $factura->id,
            'motivo_catalogo' => '01',
            'detalle' => 'Mora',
            'importe' => 5,
        ])
        ->assertSessionHas('error');

    expect(ElectronicDocument::where('tipo', 'nota_credito')->value('sunat_estado'))->toBe('pendiente')
        ->and(ElectronicDocument::where('tipo', 'nota_debito')->value('sunat_estado'))->toBe('pendiente');
});

test('la nota de débito valida el motivo del catálogo 10', function () {
    [, $factura] = ventaConFactura();
    $vendedor = vendedorUser();

    $this->actingAs($vendedor)
        ->post(route('vendedor.notas-debito.store', ['current_team' => $vendedor->currentTeam]), [
            'electronic_document_id' => $factura->id,
            'motivo_catalogo' => '09',
            'detalle' => 'Motivo inválido',
            'importe' => 5,
        ])
        ->assertSessionHasErrors('motivo_catalogo');
});
