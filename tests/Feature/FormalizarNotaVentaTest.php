<?php

use App\Actions\Billing\FormalizarNotaVenta;
use App\Contracts\SunatClientInterface;
use App\Models\Client;
use App\Models\CompanySetting;
use App\Models\ElectronicDocument;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SalePayment;
use App\Models\Service;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Storage::fake('local');
    config(['billing.envio_diferido_horas' => 6, 'billing.sunat.cert_path' => base_path('tests/Fixtures/certificates/test-certificate.pem')]);
    $this->mock(SunatClientInterface::class)->shouldNotReceive('send');
});

function notaParaFormalizar(array $extra = []): Sale
{
    $sale = Sale::factory()->create([
        'client_id' => (Client::query()->where('tipo_documento', 'ruc')->where('numero_documento', '20123456789')->first()
            ?? Client::factory()->create(['tipo_documento' => 'ruc', 'numero_documento' => '20123456789', 'estado_contribuyente' => 'ACTIVO', 'condicion_domicilio' => 'HABIDO', 'direccion_fiscal' => 'Av. Principal 100']))->id,
        'estado' => 'confirmada', 'comprobante_tipo' => 'nota_venta', 'numero_nota_venta' => 'NV-'.str_pad((string) (Sale::query()->count() + 1), 4, '0', STR_PAD_LEFT),
        'subtotal' => 500, 'igv' => 90, 'total' => 590, ...$extra,
    ]);
    SaleItem::factory()->forService(Service::factory()->create())->create(['sale_id' => $sale->id, 'cantidad' => 1, 'precio_unitario' => 590, 'subtotal' => 590]);

    return $sale;
}

test('formalizar y repetir la solicitud conserva la misma venta cobros cuotas y detalle', function () {
    $sale = notaParaFormalizar(['condicion_pago' => 'credito']);
    $cuota = $sale->installments()->create(['numero_cuota' => 1, 'fecha_vencimiento' => today()->addDays(30), 'monto' => 590, 'estado' => 'pendiente']);
    $pago = SalePayment::factory()->create(['sale_id' => $sale->id, 'installment_id' => $cuota->id, 'monto' => 50, 'forma_pago' => 'transferencia']);
    $linea = $sale->items()->firstOrFail();
    $primero = app(FormalizarNotaVenta::class)->handle($sale, 'factura');
    $segundo = app(FormalizarNotaVenta::class)->handle($sale, 'factura');

    expect($segundo->id)->toBe($primero->id)
        ->and($sale->refresh()->comprobante_tipo)->toBe('factura')
        ->and($sale->numero_nota_venta)->toBe('NV-0001')
        ->and($sale->electronicDocuments()->count())->toBe(1)
        ->and($sale->payments()->pluck('id')->all())->toBe([$pago->id])
        ->and($sale->installments()->pluck('id')->all())->toBe([$cuota->id])
        ->and($sale->items()->pluck('id')->all())->toBe([$linea->id])
        ->and($cuota->refresh()->monto)->toBe('590.00')
        ->and($primero->sunat_estado)->toBe('por_enviar');
});

test('la formalizacion no permite factura con DNI y revierte el cambio', function () {
    $sale = notaParaFormalizar(['client_id' => Client::factory()->create(['tipo_documento' => 'dni'])->id]);
    expect(fn () => app(FormalizarNotaVenta::class)->handle($sale, 'factura'))->toThrow(ValidationException::class, 'RUC');
    expect($sale->refresh()->esNotaVenta())->toBeTrue()->and($sale->electronicDocuments()->count())->toBe(0);
    expect(app(FormalizarNotaVenta::class)->handle($sale, 'boleta')->tipo)->toBe('boleta');
});

test('la factura formalizada resuelve detraccion sin crear pagos nuevos', function () {
    CompanySetting::current()->update(['cuenta_detraccion' => '00123456789']);
    $sale = notaParaFormalizar(['subtotal' => 1000, 'igv' => 180, 'total' => 1180]);
    $sale->items()->update(['precio_unitario' => 1180, 'subtotal' => 1180]);
    SalePayment::factory()->create(['sale_id' => $sale->id, 'monto' => 1038.40, 'forma_pago' => 'transferencia']);
    $documento = app(FormalizarNotaVenta::class)->handle($sale, 'factura');

    expect((float) $sale->refresh()->monto_detraccion_snapshot)->toBe(141.6)
        ->and($sale->payments()->count())->toBe(1)
        ->and((float) $sale->payments()->sum('monto'))->toBe(1038.4)
        ->and($documento->datos_emision['detraccion']['monto'])->toBe(141.6);
});

test('si la NV ya cobro el total no falsea una devolucion para emitir con detraccion', function () {
    CompanySetting::current()->update(['cuenta_detraccion' => '00123456789']);
    $sale = notaParaFormalizar(['subtotal' => 1000, 'igv' => 180, 'total' => 1180]);
    SalePayment::factory()->create(['sale_id' => $sale->id, 'monto' => 1180, 'forma_pago' => 'transferencia']);
    expect(fn () => app(FormalizarNotaVenta::class)->handle($sale, 'factura'))->toThrow(ValidationException::class, 'cobros');
    expect($sale->refresh()->esNotaVenta())->toBeTrue()->and($sale->payments()->sum('monto'))->toBe('1180.00')->and($sale->refunds()->count())->toBe(0)->and(ElectronicDocument::count())->toBe(0);
});

test('solo NV confirmadas se formalizan y otro CPE nunca se duplica', function () {
    $sale = notaParaFormalizar(['estado' => 'borrador']);
    expect(fn () => app(FormalizarNotaVenta::class)->handle($sale, 'boleta'))->toThrow(ValidationException::class);
    $sale->update(['estado' => 'confirmada']);
    ElectronicDocument::factory()->create(['sale_id' => $sale->id, 'tipo' => 'boleta']);
    expect(fn () => app(FormalizarNotaVenta::class)->handle($sale, 'factura'))->toThrow(ValidationException::class);
    expect($sale->electronicDocuments()->count())->toBe(1);
});

test('la ruta de formalizacion respeta vendedor y sede y valida el tipo fiscal', function () {
    $vendedor = vendedorUser();
    $sale = notaParaFormalizar(['vendedor_id' => $vendedor->id]);
    $ruta = route('vendedor.ventas.formalizar-nv', ['current_team' => $vendedor->currentTeam, 'sale' => $sale]);
    $this->actingAs($vendedor)->post($ruta, ['comprobante_tipo' => 'nota_venta'])->assertSessionHasErrors('comprobante_tipo');
    $this->actingAs($vendedor)->post($ruta, ['comprobante_tipo' => 'factura'])->assertRedirect();
    $ajena = notaParaFormalizar();
    $this->actingAs($vendedor)->post(route('vendedor.ventas.formalizar-nv', ['current_team' => $vendedor->currentTeam, 'sale' => $ajena]), ['comprobante_tipo' => 'factura'])->assertNotFound();
    expect($ajena->refresh()->esNotaVenta())->toBeTrue();
});
