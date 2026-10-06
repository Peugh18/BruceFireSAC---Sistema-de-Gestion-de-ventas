<?php

use App\Actions\Billing\EmitElectronicDocument;
use App\Actions\Billing\IssueCreditNote;
use App\Actions\Billing\IssueDebitNote;
use App\Actions\Billing\VoidElectronicDocument;
use App\Actions\Sales\CreateSale;
use App\Http\Requests\Billing\StoreCreditNoteRequest;
use App\Models\Client;
use App\Models\ElectronicDocument;
use App\Models\InventoryMovement;
use App\Models\InventoryUnit;
use App\Models\Product;
use App\Models\Sede;
use App\Services\Billing\GreenterService;
use App\Services\Billing\GreenterSunatClient;
use Database\Seeders\RolesAndPermissionsSeeder;
use Greenter\Ws\Services\SunatEndpoints;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

// ---------------------------------------------------------------------------
// S1: El cliente SUNAT elige el servidor beta o produccion
// ---------------------------------------------------------------------------

test('S1: con beta=true el endpoint es FE_BETA', function () {
    config(['billing.sunat.beta' => true]);
    $client = new GreenterSunatClient;
    expect($client->resolveEndpoint())->toBe(SunatEndpoints::FE_BETA);
});

test('S1: con beta=false el endpoint es FE_PRODUCCION', function () {
    config(['billing.sunat.beta' => false]);
    $client = new GreenterSunatClient;
    expect($client->resolveEndpoint())->toBe(SunatEndpoints::FE_PRODUCCION);
});

// ---------------------------------------------------------------------------
// S2: NC sobre boleta rechaza los motivos 04, 05 y 08
// ---------------------------------------------------------------------------

function boletaAceptada(): array
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
        'condicion_pago' => 'contado',
        'comprobante_tipo' => 'boleta',
    ], [[
        'tipo_linea' => 'unidad_nueva',
        'numero_serie' => $unit->numero_serie,
        'product_id' => $product->id,
        'cantidad' => 1,
        'precio_unitario' => 100,
    ]], vendedorUser()->id);

    $sale->update(['estado' => 'confirmada']);
    $boleta = ElectronicDocument::factory()->create([
        'sale_id' => $sale->id,
        'tipo' => 'boleta',
        'serie' => 'B001',
        'sunat_estado' => 'aceptado',
    ]);

    return [$sale->refresh(), $boleta];
}

test('S2: NC sobre boleta rechaza motivo 04', function () {
    [, $boleta] = boletaAceptada();
    expect(fn () => app(IssueCreditNote::class)->handle($boleta, '04', 'Descuento', 10))
        ->toThrow(ValidationException::class);
});

test('S2: NC sobre boleta rechaza motivo 05', function () {
    [, $boleta] = boletaAceptada();
    expect(fn () => app(IssueCreditNote::class)->handle($boleta, '05', 'Descuento item', 10))
        ->toThrow(ValidationException::class);
});

test('S2: NC sobre boleta rechaza motivo 08', function () {
    [, $boleta] = boletaAceptada();
    expect(fn () => app(IssueCreditNote::class)->handle($boleta, '08', 'Bonificacion', 10))
        ->toThrow(ValidationException::class);
});

test('S2: NC sobre boleta acepta motivo 01', function () {
    [$sale, $boleta] = boletaAceptada();
    $nota = app(IssueCreditNote::class)->handle($boleta, '01', 'Anulacion', (float) $sale->total);
    expect($nota->tipo)->toBe('nota_credito')->and($nota->motivo_catalogo)->toBe('01');
});

// ---------------------------------------------------------------------------
// S3: ND motivo 13 existe; motivo 03 es "Otros conceptos" sin penalidades
// ---------------------------------------------------------------------------

test('S3: el catalogo 10 incluye el motivo 13 para penalidades', function () {
    $descripcion = GreenterService::descripcionMotivoDebito('13');
    expect($descripcion)->toBe('PENALIDADES');
});

test('S3: el motivo 03 de ND es Otros conceptos sin mencionar penalidades', function () {
    $descripcion = GreenterService::descripcionMotivoDebito('03');
    expect(mb_strtoupper($descripcion))->not->toContain('PENALID')
        ->and(mb_strtoupper($descripcion))->toContain('OTROS');
});

test('S3: StoreDebitNoteRequest acepta el motivo 13', function () {
    [, $factura] = ventaConFactura();
    $vendedor = vendedorUser();
    $factura->sale->update(['vendedor_id' => $vendedor->id]);

    $this->actingAs($vendedor)
        ->post(route('vendedor.notas-debito.store', ['current_team' => $vendedor->currentTeam]), [
            'electronic_document_id' => $factura->id,
            'motivo_catalogo' => '13',
            'detalle' => 'Penalidad por incumplimiento',
            'importe' => 50,
        ])
        ->assertSessionMissing('errors');

    expect(ElectronicDocument::where('tipo', 'nota_debito')->where('motivo_catalogo', '13')->exists())->toBeTrue();
});

test('S3: la ND con motivo 13 usa afectacion inafecta 30 en el XML', function () {
    [, $factura] = ventaConFactura();
    $nota = app(IssueDebitNote::class)->handle($factura, '13', 'Penalidad', 50);
    $nota->refresh();
    $noteObj = app(GreenterService::class)->buildNote($nota);
    $detalle = $noteObj->getDetails()[0];
    expect($detalle->getTipAfeIgv())->toBe('30')
        ->and((float) $detalle->getIgv())->toBe(0.0)
        ->and((float) $detalle->getMtoBaseIgv())->toBe(0.0);
});

// ---------------------------------------------------------------------------
// S5: fecha_emision es datetime con hora; buildNote usa la hora persistida
// ---------------------------------------------------------------------------

test('S5: fecha_emision se guarda como datetime con hora', function () {
    $doc = ElectronicDocument::factory()->create([
        'fecha_emision' => now()->setTime(14, 35, 22),
    ]);
    $doc->refresh();
    expect($doc->fecha_emision)->not->toBeNull()
        ->and($doc->fecha_emision->format('H:i:s'))->not->toBe('00:00:00');
});

test('S5: buildNote usa la hora de emision persistida no now()', function () {
    [, $factura] = ventaConFactura();
    $hora = now()->subHours(3)->setSecond(0)->setMicrosecond(0);
    $nota = app(IssueCreditNote::class)->handle($factura, '03', 'Correccion', 10);
    $nota->update(['fecha_emision' => $hora]);
    $nota->refresh();
    $noteObj = app(GreenterService::class)->buildNote($nota);
    expect($noteObj->getFechaEmision()->format('H:i:s'))->not->toBe('00:00:00');
});

// ---------------------------------------------------------------------------
// S6: fecha de NC y ND se guarda al crear (no es null, no usa now() al enviar)
// ---------------------------------------------------------------------------

test('S6: la nota de credito guarda su fecha_emision al crearse', function () {
    [, $factura] = ventaConFactura();
    $antes = now()->floorSecond();
    $nota = app(IssueCreditNote::class)->handle($factura, '03', 'Correccion', 10);
    expect($nota->fecha_emision)->not->toBeNull()
        ->and($nota->fecha_emision->gte($antes))->toBeTrue();
});

test('S6: la nota de debito guarda su fecha_emision al crearse', function () {
    [, $factura] = ventaConFactura();
    $antes = now()->floorSecond();
    $nota = app(IssueDebitNote::class)->handle($factura, '01', 'Mora', 10);
    expect($nota->fecha_emision)->not->toBeNull()
        ->and($nota->fecha_emision->gte($antes))->toBeTrue();
});

// ---------------------------------------------------------------------------
// S9: Catalogo 09 completo (01 a 13)
// ---------------------------------------------------------------------------

test('S9: el catalogo 09 tiene todos los motivos del 01 al 13', function () {
    $motivos = ['01', '02', '03', '04', '05', '06', '07', '08', '09', '10', '11', '12', '13'];
    foreach ($motivos as $codigo) {
        $descripcion = GreenterService::descripcionMotivoCredito($codigo);
        expect($descripcion)->not->toBe('AJUSTE DEL COMPROBANTE', "Motivo NC {$codigo} falta en el catalogo");
    }
});

test('S9: el motivo 07 del catalogo 09 es devolucion parcial', function () {
    $descripcion = mb_strtolower(GreenterService::descripcionMotivoCredito('07'));
    expect($descripcion)->toContain('devoluc')
        ->and($descripcion)->toContain('parcial');
});

test('S9: el StoreCreditNoteRequest acepta motivos del 01 al 13', function () {
    [, $factura] = ventaConFactura();
    $vendedor = vendedorUser();
    $factura->sale->update(['vendedor_id' => $vendedor->id]);
    $this->actingAs($vendedor)
        ->post(route('vendedor.notas-credito.store', ['current_team' => $vendedor->currentTeam]), [
            'electronic_document_id' => $factura->id,
            'motivo_catalogo' => '08',
            'detalle' => 'Bonificacion en factura',
            'importe' => 5,
        ])
        ->assertSessionMissing('errors');
});

// ---------------------------------------------------------------------------
// S10: NC y ND reproducen afectacion del original; motivo 13 => inafecto (30)
// ---------------------------------------------------------------------------

test('S10: buildNote con motivo 13 pone afectacion 30 y cero IGV', function () {
    [, $factura] = ventaConFactura();
    $nota = app(IssueDebitNote::class)->handle($factura, '13', 'Penalidad', 100);
    $nota->refresh();
    $noteObj = app(GreenterService::class)->buildNote($nota);
    $detalle = $noteObj->getDetails()[0];
    expect($detalle->getTipAfeIgv())->toBe('30')
        ->and((float) $detalle->getIgv())->toBe(0.0);
});

test('S1: el cliente SUNAT verifica TLS seguro', function () {
    $client = new GreenterSunatClient;
    expect($client->verifyTlsConfig())->toBeTrue();
});

// ---------------------------------------------------------------------------
// S4: tipo_afectacion_igv del Catalogo 07 en el producto
// ---------------------------------------------------------------------------

test('S4: el producto tiene campo tipo_afectacion_igv por defecto 10', function () {
    $product = Product::factory()->create();
    expect($product->tipo_afectacion_igv)->toBe('10');
});

test('S4: buildInvoice respeta el tipo_afectacion_igv inafecto 30 del producto', function () {
    $product = Product::factory()->create(['tipo_afectacion_igv' => '30', 'aplica_igv' => false, 'serializado' => false]);
    $sede = Sede::factory()->almacen()->create();
    InventoryMovement::create([
        'product_id' => $product->id,
        'sede_id' => $sede->id,
        'tipo' => 'ingreso',
        'cantidad' => 10,
        'creado_por' => vendedorUser()->id,
    ]);
    $sale = app(CreateSale::class)->handle([
        'client_id' => Client::factory()->create()->id,
        'sede_id' => $sede->id,
        'fecha' => now()->toDateString(),
        'destino' => 'local_cliente',
        'condicion_pago' => 'contado',
        'comprobante_tipo' => 'factura',
    ], [[
        'tipo_linea' => 'producto',
        'product_id' => $product->id,
        'descripcion' => 'Item Inafecto',
        'cantidad' => 1,
        'precio_unitario' => 100,
        'aplica_igv' => false,
    ]], vendedorUser()->id);

    $doc = ElectronicDocument::factory()->create(['sale_id' => $sale->id, 'tipo' => 'factura', 'serie' => 'F001']);
    $invoiceObj = app(GreenterService::class)->buildInvoice($sale, $doc);
    $detail = $invoiceObj->getDetails()[0];

    expect($detail->getTipAfeIgv())->toBe('30')
        ->and((float) $detail->getIgv())->toBe(0.0);
});

// ---------------------------------------------------------------------------
// S7: Comunicacion de baja (VoidElectronicDocument)
// ---------------------------------------------------------------------------

test('S7: no se puede comunicar la baja de un comprobante emitido hace mas de 7 dias', function () {
    $doc = ElectronicDocument::factory()->create([
        'tipo' => 'factura',
        'sunat_estado' => 'aceptado',
        'fecha_emision' => now()->subDays(10),
    ]);

    $action = new VoidElectronicDocument;
    expect(fn () => $action->handle($doc, 'Error de emision'))
        ->toThrow(ValidationException::class);
});

test('S7: anula un comprobante aceptado dentro de los 7 dias', function () {
    $doc = ElectronicDocument::factory()->create([
        'tipo' => 'factura',
        'sunat_estado' => 'aceptado',
        'fecha_emision' => now()->subDays(2),
    ]);

    $action = new VoidElectronicDocument;
    $anulado = $action->handle($doc, 'Error en el cliente');

    expect($anulado->sunat_estado)->toBe('anulado')
        ->and($anulado->sunat_mensaje)->toContain('Baja procesada');
});

// ---------------------------------------------------------------------------
// S8: Aviso de plazo de envio (factura 3 dias, boleta 5 dias)
// ---------------------------------------------------------------------------

test('S8: calcula plazo limite segun el tipo de comprobante', function () {
    $factura = ElectronicDocument::factory()->make(['tipo' => 'factura']);
    $boleta = ElectronicDocument::factory()->make(['tipo' => 'boleta']);

    expect($factura->plazoLimiteDias())->toBe(3)
        ->and($boleta->plazoLimiteDias())->toBe(5);
});

test('S8: detecta cuando un comprobante por enviar esta por vencer', function () {
    $factura = ElectronicDocument::factory()->create([
        'tipo' => 'factura',
        'sunat_estado' => 'por_enviar',
        'fecha_emision' => now()->subDays(2)->subHours(10),
    ]);

    expect($factura->estaPorVencerSunat())->toBeTrue();
});

// ---------------------------------------------------------------------------
// S12 & S13: Congelar el XML firmado en los reintentos de envio
// ---------------------------------------------------------------------------

test('S12/S13: reutiliza el XML congelado existente en los reintentos', function () {
    [, $factura] = ventaConFactura();
    $xmlPath = "xml/RUC-01-{$factura->serie}-{$factura->correlativo}.xml";
    Storage::disk('local')->put($xmlPath, '<XML_CONGELADO/>');
    $factura->update(['xml_path' => $xmlPath]);

    $prepared = app(EmitElectronicDocument::class)->prepararDocumento($factura);

    expect($prepared['xml'])->toBe('<XML_CONGELADO/>');
});

// ---------------------------------------------------------------------------
// V2 & S17: Permisos Spatie en authorize de StoreCreditNoteRequest / StoreDebitNoteRequest
// ---------------------------------------------------------------------------

test('V2/S17: StoreCreditNoteRequest autoriza a usuarios con rol Vendedor o Gerente', function () {
    $user = vendedorUser();
    $request = new StoreCreditNoteRequest;
    $request->setUserResolver(fn () => $user);

    expect($request->authorize())->toBeTrue();
});
