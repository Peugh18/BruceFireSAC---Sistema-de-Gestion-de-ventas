<?php

use App\Actions\Sales\ConfirmSale;
use App\Models\Client;
use App\Models\CompanySetting;
use App\Models\ElectronicDocument;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SalePayment;
use App\Models\Service;
use App\Models\SunatDetraccionRegla;
use App\Models\User;
use App\Services\Billing\ComprobantePdfService;
use App\Services\Billing\DatosEmision;
use App\Services\Billing\DetraccionCalculator;
use App\Services\Billing\GreenterService;
use App\Support\CatalogosSunat;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

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

    expect($pagos)->toHaveCount(1)
        ->and($pagos[0]->forma_pago)->toBe('transferencia')
        ->and((float) $pagos[0]->monto)->toBe(1038.4)
        ->and($pagos[0]->es_detraccion)->toBeFalse();
});

test('clasificacion y monto emitidos se conservan aunque cambie el catalogo fiscal', function () {
    CompanySetting::current()->update(['cuenta_detraccion' => '00-123-456789']);
    $sale = ventaDeServicioConDetraccion('factura', 'contado', ['estado' => 'borrador', 'medio_pago' => 'transferencia']);
    $sale->items()->firstOrFail()->service->update(['codigo_detraccion' => '022']);

    app(ConfirmSale::class)->handle($sale);

    expect($sale->fresh()->codigo_detraccion_snapshot)->toBe('022');
    expect((float) $sale->fresh()->monto_detraccion_snapshot)->toBe(141.6);
    $sale->items()->firstOrFail()->service->update(['codigo_detraccion' => '037']);
    config(['billing.detraccion.tasa' => 0.15, 'billing.detraccion.monto_minimo' => 2000]);
    $sale = $sale->fresh();
    $resultado = app(DetraccionCalculator::class)->paraVenta($sale);
    $invoice = app(GreenterService::class)->buildInvoice($sale, documentoDe($sale));

    expect($resultado)->toBe(['aplica' => true, 'monto' => 141.6, 'codigo_bien' => '022', 'porcentaje' => 12.0]);
    expect($invoice->getDetraccion()->getCodBienDetraccion())->toBe('022');
    expect($invoice->getDetraccion()->getMount())->toBe(141.6);
    expect($invoice->getDetraccion()->getPercent())->toBe(12.0);
    expect((float) $sale->payments()->sum('monto'))->toBe(1038.4);
});

test('la factura impresa muestra la detraccion como sunat y el monto neto pendiente del credito', function () {
    CompanySetting::current()->update(['cuenta_detraccion' => '00-123-456789']);
    $sale = ventaDeServicioConDetraccion('factura', 'credito');
    $sale->installments()->create(['numero_cuota' => 1, 'fecha_vencimiento' => now()->addDays(30), 'monto' => 1180, 'estado' => 'pendiente']);
    $documento = documentoDe($sale);
    guardarXmlOriginalDePrueba($documento);
    $codigo = app(DetraccionCalculator::class)->paraVenta($sale->fresh())['codigo_bien'];

    $documento->refresh();
    $html = app(ComprobantePdfService::class)->html($documento, (string) Storage::disk('local')->get($documento->xml_path));

    expect($html)->toContain('Información de la detracción')
        ->toContain(CatalogosSunat::detraccionConNombre($codigo))
        ->toContain('001 - Depósito en cuenta')
        ->toContain('00-123-456789')
        ->toContain('141.60')
        ->toContain('Monto neto pendiente de pago')
        ->toContain('1,038.40');
});

test('el extintor va a sunat con su codigo de producto del catalogo 25', function () {
    $extintor = Product::factory()->create(['categoria' => 'extintor', 'agente' => 'pqs', 'precio_venta' => 118]);
    $sale = Sale::factory()->create(['comprobante_tipo' => 'boleta', 'subtotal' => 100, 'igv' => 18, 'total' => 118]);
    SaleItem::factory()->create(['sale_id' => $sale->id, 'product_id' => $extintor->id, 'service_id' => null, 'cantidad' => 1, 'precio_unitario' => 118, 'descuento' => 0, 'subtotal' => 118]);

    $boleta = app(GreenterService::class)->buildInvoice($sale->fresh(), documentoDe($sale));

    expect($boleta->getDetails()[0]->getCodProdSunat())->toBe('46191601');
});

test('el limite de servicios se evalua sobre el total con igv y solo aplica por encima de 700', function (float $total, bool $aplica) {
    CompanySetting::current()->update(['cuenta_detraccion' => '00-123-456789']);
    $base = round($total / 1.18, 2);
    $sale = ventaDeServicioConDetraccion('factura', extra: ['total' => $total, 'subtotal' => $base, 'igv' => round($total - $base, 2), 'estado' => 'borrador']);
    $sale->items()->firstOrFail()->update(['precio_unitario' => $total, 'subtotal' => $total]);

    $invoice = app(GreenterService::class)->buildInvoice($sale->fresh(), documentoDe($sale));
    $xml = app(GreenterService::class)->sign($invoice);
    $datos = app(DatosEmision::class)->desdeXml($xml);

    expect($invoice->getTipoOperacion())->toBe($aplica ? '1001' : '0101')
        ->and($datos['detraccion']['aplica'])->toBe($aplica);
    if ($aplica) {
        expect($datos['detraccion']['monto'])->toBe(round($total * 0.12, 2))
            ->and($datos['detraccion']['porcentaje'])->toBe(12.0)
            ->and($datos['detraccion']['medio_pago'])->toBe('001');
    } else {
        expect($invoice->getDetraccion())->toBeNull();
    }
})->with([[699.99, false], [700.0, false], [700.01, true]]);

test('el pdf conserva medio porcentaje y cuenta de detraccion del xml aunque cambie la configuracion', function () {
    CompanySetting::current()->update(['cuenta_detraccion' => '00-123-456789']);
    config(['billing.detraccion.cod_medio_pago' => '003']);
    $sale = ventaDeServicioConDetraccion('factura', 'credito');
    $sale->installments()->create(['numero_cuota' => 1, 'fecha_vencimiento' => now()->addDays(30), 'monto' => 1180, 'estado' => 'pendiente']);
    $documento = documentoDe($sale);
    guardarXmlOriginalDePrueba($documento);
    $xml = (string) Storage::disk('local')->get($documento->fresh()->xml_path);
    $datos = app(DatosEmision::class)->recuperar($documento);

    SunatDetraccionRegla::query()->update(['porcentaje' => 10]);
    unset($datos['detraccion']['medio_pago'], $datos['detraccion']['porcentaje']);
    $documento->forceFill(['datos_emision' => $datos])->saveQuietly();
    $datos = app(DatosEmision::class)->recuperar($documento->fresh());

    expect($datos['detraccion']['porcentaje'])->toBe(12.0)
        ->and($datos['detraccion']['medio_pago'])->toBe('003');

    config(['billing.detraccion.cod_medio_pago' => '001']);
    CompanySetting::current()->update(['cuenta_detraccion' => '00-999-999999']);
    $sale->update(['total' => 5000]);
    $html = app(ComprobantePdfService::class)->html($documento->fresh(), $xml);

    expect($html)->toContain('003 - Transferencia de fondos')
        ->toContain('Porcentaje: 12 %')
        ->toContain('00-123-456789')
        ->toContain('1,038.40')
        ->not->toContain('00-999-999999');
});

test('el vendedor recibe las reglas de detraccion y clasificaciones al crear o editar una venta', function () {
    $vendedor = User::factory()->create();
    $vendedor->assignRole('Vendedor');
    CompanySetting::current()->update(['cuenta_detraccion' => '00-123-456789']);
    $servicio = Service::factory()->create(['codigo_detraccion' => '022', 'tipo_afectacion_igv' => '10']);
    $venta = Sale::factory()->create(['vendedor_id' => $vendedor->id, 'estado' => 'borrador']);

    foreach (['create' => [], 'edit' => ['sale' => $venta]] as $accion => $parametros) {
        $this->actingAs($vendedor)
            ->get(route('vendedor.ventas.'.$accion, ['current_team' => $vendedor->currentTeam, ...$parametros]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('reglasDetraccion.monto_minimo', 700)
                ->where('reglasDetraccion.porcentaje', 12)
                ->where('reglasDetraccion.cuenta_bn', '00-123-456789')
                ->where('reglasDetraccion.servicios.0.id', $servicio->id)
                ->where('reglasDetraccion.servicios.0.codigo', '022'));
    }
});
