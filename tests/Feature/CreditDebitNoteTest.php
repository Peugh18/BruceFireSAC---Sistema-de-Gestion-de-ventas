<?php

use App\Actions\Billing\EmitElectronicDocument;
use App\Actions\Billing\IssueCreditNote;
use App\Actions\Billing\IssueDebitNote;
use App\Contracts\SunatClientInterface;
use App\Models\CashRegister;
use App\Models\ElectronicDocument;
use App\Models\InventoryMovement;
use App\Models\Sale;
use App\Models\SalePayment;
use App\Services\Billing\ComprobantePdfService;
use App\Services\Billing\GreenterService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Validation\ValidationException;
use Tests\Fixtures\SunatSoloEnvio;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('una nota de crédito de anulación total devuelve stock kardex y anula la venta', function () {
    [$sale, $factura, $unit] = ventaConFactura('credito_30');
    SalePayment::factory()->create(['sale_id' => $sale->id, 'monto' => 10]);
    // Si lo cobrado fue en efectivo, la devolución sale de la caja abierta.
    CashRegister::factory()->create(['vendedor_id' => $sale->vendedor_id]);

    $nota = aceptarNota(app(IssueCreditNote::class)->handle($factura, '01', 'Anulación', (float) $sale->total));

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
    aceptarNota(app(IssueCreditNote::class)->handle($factura, '01', 'Anulación', (float) $sale->total));

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

test('quien también es gerente emite notas desde el detalle y si SUNAT no responde quedan pendientes', function () {
    [, $factura] = ventaConFactura();
    $this->app->bind(SunatClientInterface::class, fn () => new class extends SunatSoloEnvio
    {
        public function send(string $xmlSigned, string $documentName): array
        {
            throw new RuntimeException('SUNAT no responde');
        }
    });
    $vendedor = vendedorUser();
    // Un vendedor solo pide la nota; quien además es Gerente la emite directo.
    $vendedor->assignRole('Gerente');
    $factura->sale->update(['vendedor_id' => $vendedor->id]);
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

test('el pdf de una nota de crédito muestra la misma línea e importe que su xml', function () {
    config(['billing.sunat.cert_path' => base_path('tests/Fixtures/certificates/test-certificate.pem')]);
    [$sale, $factura] = ventaConFactura();
    $credito = app(IssueCreditNote::class)->handle($factura, '04', 'Rebaja acordada', 59);
    ['xml' => $xml] = app(EmitElectronicDocument::class)->prepararDocumento($credito);
    $credito->refresh();

    $html = app(ComprobantePdfService::class)->html($credito, $xml);

    expect($credito->pdf_path)->not->toBeNull()
        ->and($html)->toContain('DESCUENTO GLOBAL')
        ->and($html)->toContain('S/ 59.00')
        ->and($html)->not->toContain($sale->items->first()->product->nombre);
});
