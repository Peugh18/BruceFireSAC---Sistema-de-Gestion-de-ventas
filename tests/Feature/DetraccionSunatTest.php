<?php

use App\Actions\Sales\ConfirmSale;
use App\Models\Client;
use App\Models\CompanySetting;
use App\Models\ElectronicDocument;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SalePayment;
use App\Models\Service;
use App\Services\Billing\GreenterService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    // Al confirmar una factura se firma el XML: se usa el certificado de prueba del proyecto.
    config(['billing.sunat.cert_path' => base_path('tests/Fixtures/certificates/test-certificate.pem')]);
});

/**
 * Venta de un servicio de S/ 1,180 (detracción 12 % = S/ 141.60 si es factura).
 */
function ventaDeServicioConDetraccion(string $comprobante, string $condicion = 'contado', array $extra = []): Sale
{
    $sale = Sale::factory()->create([
        'client_id' => Client::factory()->create(['tipo_documento' => 'ruc', 'numero_documento' => '20'.fake()->unique()->numerify('#########')])->id,
        'comprobante_tipo' => $comprobante,
        'condicion_pago' => $condicion,
        'subtotal' => 1000,
        'igv' => 180,
        'total' => 1180,
        ...$extra,
    ]);
    SaleItem::factory()->forService(Service::factory()->create(['precio_venta' => 1000]))->create([
        'sale_id' => $sale->id, 'cantidad' => 1, 'precio_unitario' => 1000, 'descuento' => 0, 'subtotal' => 1000,
    ]);

    return $sale;
}

function documentoDe(Sale $sale): ElectronicDocument
{
    return ElectronicDocument::create([
        'sale_id' => $sale->id,
        'tipo' => $sale->comprobante_tipo,
        'serie' => $sale->comprobante_tipo === 'factura' ? 'F001' : 'B001',
        'correlativo' => ElectronicDocument::count() + 1,
    ]);
}

test('la factura con detraccion va como operacion 1001 con la cuenta del banco de la nacion', function () {
    CompanySetting::current()->update(['cuenta_detraccion' => '00-123-456789']);
    $sale = ventaDeServicioConDetraccion('factura');

    $invoice = app(GreenterService::class)->buildInvoice($sale, documentoDe($sale));

    expect($invoice->getTipoOperacion())->toBe('1001')
        ->and($invoice->getDetraccion()->getCtaBanco())->toBe('00-123-456789')
        ->and($invoice->getDetraccion()->getMount())->toBe(141.6);
});

test('a credito el monto pendiente y las cuotas que van a sunat descuentan la detraccion', function () {
    CompanySetting::current()->update(['cuenta_detraccion' => '00-123-456789']);
    $sale = ventaDeServicioConDetraccion('factura', 'credito');
    $sale->installments()->create(['numero_cuota' => 1, 'fecha_vencimiento' => now()->addDays(15), 'monto' => 590, 'estado' => 'pendiente']);
    $sale->installments()->create(['numero_cuota' => 2, 'fecha_vencimiento' => now()->addDays(30), 'monto' => 590, 'estado' => 'pendiente']);

    $invoice = app(GreenterService::class)->buildInvoice($sale->fresh(), documentoDe($sale));
    $cuotas = collect($invoice->getCuotas())->map(fn ($cuota) => $cuota->getMonto())->all();

    expect($invoice->getFormaPago()->getMonto())->toBe(1038.4)
        ->and($cuotas)->toBe([590.0, 448.4])
        ->and(array_sum($cuotas))->toBe(1038.4);
});

test('la boleta nunca lleva detraccion', function () {
    $sale = ventaDeServicioConDetraccion('boleta');

    $invoice = app(GreenterService::class)->buildInvoice($sale, documentoDe($sale));

    expect($invoice->getTipoOperacion())->toBe('0101')
        ->and($invoice->getDetraccion())->toBeNull();
});

test('no deja emitir una factura con detraccion si falta la cuenta del banco de la nacion', function () {
    CompanySetting::current()->update(['cuenta_detraccion' => null]);
    $sale = ventaDeServicioConDetraccion('factura', 'contado', ['estado' => 'borrador']);

    expect(fn () => app(ConfirmSale::class)->handle($sale))
        ->toThrow(ValidationException::class, 'Banco de la Nación');
});

test('al contado con detraccion el cobro en caja es el total menos la detraccion', function () {
    CompanySetting::current()->update(['cuenta_detraccion' => '00-123-456789']);
    $sale = ventaDeServicioConDetraccion('factura', 'contado', ['estado' => 'borrador', 'medio_pago' => 'transferencia']);

    app(ConfirmSale::class)->handle($sale);

    $pagos = SalePayment::where('sale_id', $sale->id)->orderBy('id')->get();

    expect($pagos)->toHaveCount(2)
        ->and($pagos[0]->forma_pago)->toBe('transferencia')
        ->and((float) $pagos[0]->monto)->toBe(1038.4)
        ->and($pagos[1]->forma_pago)->toBe('deposito')
        ->and((float) $pagos[1]->monto)->toBe(141.6);
});
