<?php

use App\Actions\Billing\EmitElectronicDocument;
use App\Actions\Billing\IssueCreditNote;
use App\Actions\Billing\IssueDebitNote;
use App\Actions\Sales\CreateSale;
use App\Contracts\SunatClientInterface;
use App\Models\Client;
use App\Models\CompanySetting;
use App\Models\ElectronicDocument;
use App\Models\InventoryUnit;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Sede;
use App\Models\Service;
use App\Models\User;
use App\Services\Billing\AfectacionIgv;
use App\Services\Billing\ComprobantePdfService;
use App\Services\Billing\DesgloseNota;
use App\Services\Billing\GreenterService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\Fixtures\SunatSoloEnvio;

beforeEach(function () {
    config(['billing.sunat.cert_path' => base_path('tests/Fixtures/certificates/test-certificate.pem')]);
});
function documentoFaseAPendientes(string $afectacion = '10'): ElectronicDocument
{
    CompanySetting::factory()->create();
    $sale = Sale::factory()->create(['comprobante_tipo' => 'boleta', 'subtotal' => 100, 'igv' => 18, 'total' => 118]);
    SaleItem::factory()->create(['sale_id' => $sale->id, 'product_id' => Product::factory()->create(['nombre' => 'PRODUCTO ORIGINAL', 'tipo_afectacion_igv' => $afectacion])->id, 'service_id' => null, 'cantidad' => 1, 'precio_unitario' => 118, 'subtotal' => 118, 'descuento' => 0]);

    return ElectronicDocument::factory()->create(['sale_id' => $sale->id, 'tipo' => 'boleta', 'serie' => 'B001', 'sunat_estado' => 'por_enviar']);
}
test('persiste el cliente y las líneas del XML firmado', function () {
    Storage::fake('local');
    $document = documentoFaseAPendientes();
    app(EmitElectronicDocument::class)->prepararDocumento($document);
    expect($document->refresh()->datos_emision)->not->toBeNull();
    expect($document->datos_emision['cliente']['numero_documento'])->toBe($document->sale->client->numero_documento);
    expect($document->datos_emision['lineas'][0]['nombre'])->toBe('PRODUCTO ORIGINAL');
});
test('el PDF histórico conserva cliente y producto aunque cambie el catálogo', function () {
    Storage::fake('local');
    $document = documentoFaseAPendientes();
    $document->sale->client->update(['razon_social' => 'CLIENTE ORIGINAL']);
    $prepared = app(EmitElectronicDocument::class)->prepararDocumento($document);
    $document->update(['sunat_estado' => 'aceptado']);
    $document->sale->client->update(['razon_social' => 'CLIENTE MUTADO']);
    $document->sale->items->first()->product->update(['nombre' => 'PRODUCTO MUTADO']);
    $html = app(ComprobantePdfService::class)->html($document->fresh(), $prepared['xml']);
    expect($html)->toContain('CLIENTE ORIGINAL')->toContain('PRODUCTO ORIGINAL')->not->toContain('CLIENTE MUTADO')->not->toContain('PRODUCTO MUTADO');
});
test('la baja RC conserva los importes y el cliente del XML original', function () {
    Storage::fake('local');
    $document = documentoFaseAPendientes();
    app(EmitElectronicDocument::class)->prepararDocumento($document);
    $clienteOriginal = $document->sale->client->numero_documento;
    $document->update(['sunat_estado' => 'aceptado']);
    $document->sale->client->update(['numero_documento' => '99999999']);
    $document->sale->update(['total' => 999, 'subtotal' => 999, 'igv' => 0]);
    $detail = app(GreenterService::class)->buildBaja($document->fresh(), 'No entregado', 1)->getDetails()[0];
    expect($detail->getClienteNro())->toBe($clienteOriginal);
    expect((float) $detail->getTotal())->toBe(118.0);
});
test('las facturas separan bases gravadas exoneradas e inafectas', function () {
    $document = documentoFaseAPendientes('20');
    $invoice = app(GreenterService::class)->buildInvoice($document->sale, $document);
    expect((float) $invoice->getMtoOperExoneradas())->toBe(118.0);
    expect((float) $invoice->getMtoIGV())->toBe(0.0);
});
test('la nota homogénea conserva la afectación exonerada del XML original', function () {
    Storage::fake('local');
    $document = documentoFaseAPendientes('20');
    app(EmitElectronicDocument::class)->prepararDocumento($document);
    $document->update(['sunat_estado' => 'aceptado']);
    $note = ElectronicDocument::factory()->create(['sale_id' => $document->sale_id, 'tipo' => 'nota_credito', 'cpe_afectado_id' => $document->id, 'motivo_catalogo' => '09', 'importe' => 59]);
    $built = app(GreenterService::class)->buildNote($note);
    expect($built->getDetails()[0]->getTipAfeIgv())->toBe('20');
    expect((float) $built->getMtoIGV())->toBe(0.0);
    expect((float) $built->getMtoOperExoneradas())->toBe(59.0);
});
test('el gerente guarda afectación explícita y conserva el campo anterior', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $gerente = User::factory()->create();
    $gerente->assignRole('Gerente');
    $product = Product::factory()->create(['aplica_igv' => false, 'tipo_afectacion_igv' => '10']);
    $this->actingAs($gerente)->put(route('gerente.productos.update', ['current_team' => $gerente->currentTeam, 'producto' => $product]), ['codigo' => $product->codigo, 'nombre' => $product->nombre, 'unidad_medida' => 'NIU', 'precio_venta' => 65, 'tipo_afectacion_igv' => '20'])->assertSessionHasNoErrors();
    $this->assertDatabaseHas('products', ['id' => $product->id, 'tipo_afectacion_igv' => '20', 'aplica_igv' => false]);
});
test('la venta conserva precio final 65 y la afectación explícita del servicio', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $service = Service::factory()->create(['tipo_afectacion_igv' => '20']);
    $user = vendedorUser();
    $sale = app(CreateSale::class)->handle(['client_id' => Client::factory()->create()->id, 'sede_id' => Sede::factory()->almacen()->create()->id, 'fecha' => '2026-10-06', 'destino' => 'local_cliente', 'condicion_pago' => 'contado', 'comprobante_tipo' => 'boleta'], [['tipo_linea' => 'servicio', 'service_id' => $service->id, 'cantidad' => 1, 'precio_unitario' => 65]], $user->id);
    expect((float) $sale->total)->toBe(65.0);
    expect((float) $sale->igv)->toBe(0.0);
    expect($sale->items->first()->tipo_afectacion_igv)->toBe('20');
});
test('NC13 sin cuotas se rechaza antes de crear una nota', function () {
    $document = documentoFaseAPendientes();
    $document->update(['sunat_estado' => 'aceptado']);
    expect(fn () => app(IssueCreditNote::class)->handle($document, '13', 'Corregir cuotas', 59))->toThrow(ValidationException::class);
    $this->assertDatabaseCount('electronic_documents', 1);
});
test('el precio gravado final 65 se desglosa sin añadir otro impuesto en venta XML y PDF', function () {
    Storage::fake('local');
    $this->seed(RolesAndPermissionsSeeder::class);
    CompanySetting::factory()->create();
    $service = Service::factory()->create(['tipo_afectacion_igv' => '10']);
    $sale = app(CreateSale::class)->handle(['client_id' => Client::factory()->create()->id, 'sede_id' => Sede::factory()->almacen()->create()->id, 'fecha' => '2026-10-06', 'destino' => 'local_cliente', 'condicion_pago' => 'contado', 'comprobante_tipo' => 'boleta'], [['tipo_linea' => 'servicio', 'service_id' => $service->id, 'cantidad' => 1, 'precio_unitario' => 65]], vendedorUser()->id);
    $document = ElectronicDocument::factory()->create(['sale_id' => $sale->id, 'tipo' => 'boleta', 'serie' => 'B001', 'sunat_estado' => 'por_enviar']);
    $prepared = app(EmitElectronicDocument::class)->prepararDocumento($document);
    expect((float) $sale->subtotal)->toBe(55.08);
    expect((float) $sale->igv)->toBe(9.92);
    expect((float) $sale->total)->toBe(65.0);
    expect($prepared['xml'])->toContain('55.08')->toContain('9.92')->toContain('65.00');
    expect(app(ComprobantePdfService::class)->html($document, $prepared['xml']))->toContain('55.08')->toContain('9.92')->toContain('65.00');
});
test('la copia del XML mantiene forma de pago y cuotas originales', function () {
    Storage::fake('local');
    $document = documentoFaseAPendientes();
    $document->sale->update(['condicion_pago' => 'credito_30']);
    $document->sale->installments()->create(['numero_cuota' => 1, 'fecha_vencimiento' => '2026-11-06', 'monto' => 118, 'estado' => 'pendiente']);
    app(EmitElectronicDocument::class)->prepararDocumento($document->fresh());
    expect($document->refresh()->datos_emision)->toHaveKey('pago');
    expect($document->datos_emision['pago']['tipo'])->toBe('Credito');
    expect((float) $document->datos_emision['pago']['cuotas'][0]['monto'])->toBe(118.0);
    expect($document->datos_emision['pago']['cuotas'][0]['fecha'])->toBe('2026-11-06');
});
test('una afectación heredada ambigua exige elección antes de vender', function () {
    $product = Product::factory()->create(['aplica_igv' => false, 'tipo_afectacion_igv' => '10']);
    expect(fn () => AfectacionIgv::codigo($product))->toThrow(ValidationException::class);
    expect($product->fresh()->tipo_afectacion_igv)->toBe('10');
});
test('el descuento de impresión conserva el importe con IGV aunque XML use base', function () {
    Storage::fake('local');
    $document = documentoFaseAPendientes();
    $document->sale->items()->first()->update(['descuento' => 11.8, 'subtotal' => 106.2]);
    $document->sale->update(['subtotal' => 90, 'igv' => 16.2, 'total' => 106.2]);
    app(EmitElectronicDocument::class)->prepararDocumento($document);
    expect($document->refresh()->datos_emision['lineas'][0]['descuento'])->toBe(11.8);
});
test('un intento fallido conserva los mismos bytes firmados al reintentar', function () {
    Storage::fake('local');
    $document = documentoFaseAPendientes();
    $prepared = app(EmitElectronicDocument::class)->prepararDocumento($document);
    $this->app->instance(SunatClientInterface::class, new class extends SunatSoloEnvio
    {
        public function send(string $xmlSigned, string $documentName): array
        {
            throw new RuntimeException('Tiempo de espera agotado');
        }
    });
    expect(fn () => app(EmitElectronicDocument::class)->sendDocument($document))->toThrow(RuntimeException::class);
    $document->sale->client->update(['razon_social' => 'CLIENTE DESPUÉS DEL INTENTO']);
    $retried = app(EmitElectronicDocument::class)->prepararDocumento($document->fresh());
    expect($retried['xml'])->toBe($prepared['xml']);
    expect($document->fresh()->enviado_at)->toBeNull();
    expect($document->fresh()->intento_envio_at)->not->toBeNull();
});
test('una venta con intento de envío incierto no se puede editar', function () {
    $document = documentoFaseAPendientes();
    $document->sale->update(['estado' => 'confirmada']);
    $document->update(['intento_envio_at' => now()]);
    expect($document->sale->fresh()->sePuedeEditar())->toBeFalse();
});
test('un PDF fiscal histórico sin XML ni copia rechaza datos del maestro actual', function () {
    $document = documentoFaseAPendientes();
    $document->update(['sunat_estado' => 'aceptado']);
    expect(fn () => app(ComprobantePdfService::class)->html($document, '<XML_CONGELADO/>'))->toThrow(RuntimeException::class);
});
test('una NC total mixta reproduce las líneas originales y una parcial se bloquea', function () {
    Storage::fake('local');
    $document = documentoFaseAPendientes('10');
    SaleItem::factory()->create(['sale_id' => $document->sale_id, 'product_id' => Product::factory()->create(['tipo_afectacion_igv' => '20'])->id, 'service_id' => null, 'cantidad' => 1, 'precio_unitario' => 50, 'subtotal' => 50, 'descuento' => 0]);
    $document->sale->update(['subtotal' => 150, 'igv' => 18, 'total' => 168]);
    app(EmitElectronicDocument::class)->prepararDocumento($document);
    $document->update(['sunat_estado' => 'aceptado']);
    $note = app(IssueCreditNote::class)->handle($document, '06', 'Devolución total', 168);
    $built = app(GreenterService::class)->buildNote($note);
    expect($built->getDetails())->toHaveCount(2);
    expect((float) $built->getMtoOperGravadas())->toBe(100.0);
    expect((float) $built->getMtoOperExoneradas())->toBe(50.0);
    expect(fn () => app(DesgloseNota::class)->calcular($document, 'nota_credito', '07', 20))->toThrow(ValidationException::class);
});
test('un intento incierto no permite descartar XML ni reutilizar su correlativo', function () {
    Storage::fake('local');
    $document = documentoFaseAPendientes();
    app(EmitElectronicDocument::class)->prepararDocumento($document);
    $document->update(['intento_envio_at' => now()]);
    $xmlPath = $document->xml_path;
    expect(fn () => app(EmitElectronicDocument::class)->descartarPorEnviar($document))->toThrow(InvalidArgumentException::class);
    $this->assertModelExists($document);
    Storage::disk('local')->assertExists($xmlPath);
});
test('la marca de revisión no valida un código fiscal desconocido', function () {
    $product = Product::factory()->create(['tipo_afectacion_igv' => '99', 'igv_revisado_at' => now()]);
    expect(fn () => AfectacionIgv::codigo($product))->toThrow(ValidationException::class);
});

test('el comprobante no agrupa afectaciones diferentes del mismo producto y precio', function () {
    $document = documentoFaseAPendientes();
    $first = $document->sale->items()->first();
    $first->update(['tipo_afectacion_igv' => '10']);
    SaleItem::factory()->create(['sale_id' => $document->sale_id, 'product_id' => $first->product_id, 'service_id' => null, 'cantidad' => 1, 'precio_unitario' => 118, 'subtotal' => 118, 'descuento' => 0, 'tipo_afectacion_igv' => '20']);
    $document->sale->update(['subtotal' => 218, 'igv' => 18, 'total' => 236]);

    $invoice = app(GreenterService::class)->buildInvoice($document->sale->fresh(), $document);

    expect($invoice->getDetails())->toHaveCount(2);
    expect((float) $invoice->getMtoOperGravadas())->toBe(100.0);
    expect((float) $invoice->getMtoOperExoneradas())->toBe(118.0);
});

test('dos unidades serializadas a 65 conservan un único desglose agrupado en venta XML y PDF', function () {
    Storage::fake('local');
    $this->seed(RolesAndPermissionsSeeder::class);
    CompanySetting::factory()->create();
    $sede = Sede::factory()->almacen()->create();
    $product = Product::factory()->create(['tipo_afectacion_igv' => '10']);
    $units = InventoryUnit::factory()->count(2)->create(['product_id' => $product->id, 'sede_almacen_id' => $sede->id, 'estado' => 'disponible']);
    $sale = app(CreateSale::class)->handle(['client_id' => Client::factory()->create()->id, 'sede_id' => $sede->id, 'fecha' => '2026-10-06', 'destino' => 'local_cliente', 'condicion_pago' => 'contado', 'comprobante_tipo' => 'boleta'], $units->map(fn ($unit) => ['tipo_linea' => 'unidad_nueva', 'product_id' => $product->id, 'numero_serie' => $unit->numero_serie, 'cantidad' => 1, 'precio_unitario' => 65])->all(), vendedorUser()->id);
    $document = ElectronicDocument::factory()->create(['sale_id' => $sale->id, 'tipo' => 'boleta', 'serie' => 'B001', 'sunat_estado' => 'por_enviar']);

    $invoice = app(GreenterService::class)->buildInvoice($sale, $document);

    expect((float) $sale->subtotal)->toBe(110.17);
    expect((float) $sale->igv)->toBe(19.83);
    expect((float) $sale->total)->toBe(130.0);
    expect($invoice->getDetails())->toHaveCount(1);
    expect((float) $invoice->getMtoIGV())->toBe(19.83);
});

test('la comunicación de baja conserva el RUC del emisor original', function () {
    Storage::fake('local');
    $document = documentoFaseAPendientes();
    app(EmitElectronicDocument::class)->prepararDocumento($document);
    $rucOriginal = CompanySetting::current()->ruc;
    CompanySetting::current()->update(['ruc' => '20999999991']);
    $document->update(['sunat_estado' => 'aceptado']);

    $baja = app(GreenterService::class)->buildBaja($document->fresh(), 'No entregado', 1);

    expect($baja->getCompany()->getRuc())->toBe($rucOriginal);
});

test('un descuento de tres céntimos se recupera sin añadir un céntimo de IGV', function () {
    Storage::fake('local');
    $document = documentoFaseAPendientes();
    $document->sale->items()->first()->update(['descuento' => 0.03, 'subtotal' => 117.97]);
    $document->sale->update(['subtotal' => 99.97, 'igv' => 18, 'total' => 117.97]);

    app(EmitElectronicDocument::class)->prepararDocumento($document);

    expect((float) $document->refresh()->datos_emision['lineas'][0]['descuento'])->toBe(0.03);
});

test('la NC total mixta conserva el descuento XML de las líneas originales', function () {
    Storage::fake('local');
    $document = documentoFaseAPendientes();
    $document->sale->items()->first()->update(['descuento' => 11.8, 'subtotal' => 106.2]);
    SaleItem::factory()->create(['sale_id' => $document->sale_id, 'product_id' => Product::factory()->create(['tipo_afectacion_igv' => '20'])->id, 'service_id' => null, 'cantidad' => 1, 'precio_unitario' => 50, 'subtotal' => 50, 'descuento' => 0]);
    $document->sale->update(['subtotal' => 140, 'igv' => 16.2, 'total' => 156.2]);
    app(EmitElectronicDocument::class)->prepararDocumento($document);
    $document->update(['sunat_estado' => 'aceptado']);
    $note = app(IssueCreditNote::class)->handle($document, '06', 'Devolución total', 156.2);

    $built = app(GreenterService::class)->buildNote($note);

    expect($built->getDetails()[0]->getDescuentos())->not->toBeEmpty();
    expect((float) $built->getDetails()[0]->getDescuentos()[0]->getMonto())->toBe(10.0);
});

test('el CSV de notas no inventa IGV y conserva al cliente emitido', function (string $tipo, string $motivo, string $montos) {
    Storage::fake('local');
    $this->seed(RolesAndPermissionsSeeder::class);
    $document = documentoFaseAPendientes('20');
    $document->sale->client->update(['razon_social' => 'CLIENTE FISCAL ORIGINAL']);
    app(EmitElectronicDocument::class)->prepararDocumento($document);
    $document->update(['sunat_estado' => 'aceptado']);
    $note = $tipo === 'nota_credito'
        ? app(IssueCreditNote::class)->handle($document, $motivo, 'Ajuste', 59)
        : app(IssueDebitNote::class)->handle($document, $motivo, 'Penalidad', 59);
    app(EmitElectronicDocument::class)->prepararDocumento($note);
    $vendedor = $document->sale->vendedor;
    $vendedor->assignRole('Vendedor');
    $document->sale->client->update(['razon_social' => 'CLIENTE MUTADO DESPUÉS']);

    $csv = $this->actingAs($vendedor)->get(route('vendedor.facturacion.excel', ['current_team' => $vendedor->currentTeam, 'ids' => [$note->id], 'desde' => today()->subDay()->toDateString(), 'hasta' => today()->addDay()->toDateString()]))->assertOk()->streamedContent();

    expect($csv)->toContain($montos)->toContain('CLIENTE FISCAL ORIGINAL')->not->toContain('CLIENTE MUTADO DESPUÉS');
})->with([
    'NC exonerada' => ['nota_credito', '09', '-59.00;0.00;-59.00'],
    'ND penalidad inafecta' => ['nota_debito', '13', '59.00;0.00;59.00'],
]);

test('el CSV no reconstruye una nota histórica que perdió su XML y su copia', function () {
    Storage::fake('local');
    $this->seed(RolesAndPermissionsSeeder::class);
    $document = documentoFaseAPendientes('20');
    app(EmitElectronicDocument::class)->prepararDocumento($document);
    $document->update(['sunat_estado' => 'aceptado']);
    $note = app(IssueCreditNote::class)->handle($document, '09', 'Ajuste', 59);
    app(EmitElectronicDocument::class)->prepararDocumento($note);
    Storage::disk('local')->delete($note->xml_path);
    $note->update(['sunat_estado' => 'aceptado', 'datos_emision' => null]);
    $vendedor = $document->sale->vendedor;
    $vendedor->assignRole('Vendedor');

    $this->actingAs($vendedor)->get(route('vendedor.facturacion.excel', ['current_team' => $vendedor->currentTeam, 'ids' => [$note->id], 'desde' => today()->subDay()->toDateString(), 'hasta' => today()->addDay()->toDateString()]))->assertUnprocessable();
});

test('una NC total mixta no acepta afectaciones que el formulario no soporta', function () {
    Storage::fake('local');
    $document = documentoFaseAPendientes();
    SaleItem::factory()->create(['sale_id' => $document->sale_id, 'product_id' => Product::factory()->create(['tipo_afectacion_igv' => '20'])->id, 'service_id' => null, 'cantidad' => 1, 'precio_unitario' => 50, 'subtotal' => 50, 'descuento' => 0]);
    $document->sale->update(['subtotal' => 150, 'igv' => 18, 'total' => 168]);
    app(EmitElectronicDocument::class)->prepararDocumento($document);
    $datos = $document->datos_emision;
    $datos['lineas'][0]['tipo_afectacion_igv'] = '40';
    $document->update(['sunat_estado' => 'aceptado', 'datos_emision' => $datos]);

    expect(fn () => app(IssueCreditNote::class)->handle($document, '06', 'Devolución total', 168))->toThrow(ValidationException::class);
    $this->assertDatabaseCount('electronic_documents', 1);
});
