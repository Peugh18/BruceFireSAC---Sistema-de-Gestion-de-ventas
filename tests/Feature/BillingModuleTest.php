<?php

use App\Actions\Billing\EmitElectronicDocument;
use App\Actions\Billing\IssueCreditNote;
use App\Actions\Billing\ReserveNextCorrelativo;
use App\Contracts\SunatClientInterface;
use App\Models\CompanySetting;
use App\Models\DocumentSeries;
use App\Models\ElectronicDocument;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Service;
use App\Services\Billing\DetraccionCalculator;
use App\Services\Billing\GreenterService;
use App\Services\Billing\NumeroEnLetrasService;
use App\Services\Billing\ResponseClassifier;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('detraccion calculator applies only for services above configured minimum', function () {
    $calculator = app(DetraccionCalculator::class);

    expect($calculator->calcular(701, true))
        ->toBe([
            'aplica' => true,
            'monto' => 84.12,
            'codigo_bien' => config('billing.detraccion.codigo_bien'),
        ])
        ->and($calculator->calcular(700, true)['aplica'])->toBeFalse()
        ->and($calculator->calcular(701, false)['aplica'])->toBeFalse();
});

test('response classifier maps sunat code ranges', function () {
    $classifier = app(ResponseClassifier::class);

    expect($classifier->classify(0))->toBe('aceptado')
        ->and($classifier->classify(2500))->toBe('rechazado')
        ->and($classifier->classify(500))->toBe('excepcion');
});

test('reserve next correlativo returns consecutive numbers for the same serie', function () {
    $action = app(ReserveNextCorrelativo::class);

    expect($action->handle('factura', 'F123'))->toBe(1)
        ->and($action->handle('factura', 'F123'))->toBe(2)
        ->and(DocumentSeries::where('tipo_comprobante', 'factura')->where('serie', 'F123')->first()->correlativo_actual)->toBe(2);
});

test('emit electronic document builds and signs a real greenter invoice, stores accepted response, cdr and xml paths', function () {
    config(['billing.sunat.cert_path' => base_path('tests/Fixtures/certificates/test-certificate.pem')]);
    config(['billing.company.ruc' => '20600000001']);

    $this->app->bind(SunatClientInterface::class, fn () => new class implements SunatClientInterface
    {
        public function send(string $xmlSigned, string $documentName): array
        {
            expect($xmlSigned)->toContain('<?xml')
                ->and($xmlSigned)->not->toContain('"document_name"')
                ->and($documentName)->toStartWith('20600000001-01-');

            return ['cdr_zip' => 'fake-zip-content', 'codigo' => 0, 'mensaje' => 'Aceptado', 'notas' => []];
        }
    });

    $sale = saleWithItem();

    $document = app(EmitElectronicDocument::class)->handle($sale);

    expect($document->sunat_estado)->toBe('aceptado')
        ->and($document->cdr_path)->not->toBeNull()
        ->and($document->xml_path)->not->toBeNull()
        ->and($document->sunat_mensaje)->toBe('Aceptado')
        ->and($document->pdf_path)->not->toBeNull()
        ->and(Storage::disk('local')->exists($document->pdf_path))->toBeTrue()
        ->and(Storage::disk('local')->get($document->pdf_path))->toStartWith('%PDF-');
});

test('emit electronic document marks accepted-with-observations code as observado', function () {
    config(['billing.sunat.cert_path' => base_path('tests/Fixtures/certificates/test-certificate.pem')]);

    $this->app->bind(SunatClientInterface::class, fn () => new class implements SunatClientInterface
    {
        public function send(string $xmlSigned, string $documentName): array
        {
            return ['cdr_zip' => 'fake-zip-content', 'codigo' => 0, 'mensaje' => 'Aceptado con observaciones', 'notas' => ['4000: Nota de observación']];
        }
    });

    $document = app(EmitElectronicDocument::class)->handle(saleWithItem());

    expect($document->sunat_estado)->toBe('observado')
        ->and($document->sunat_mensaje)->toContain('4000: Nota de observación');
});

test('emit electronic document prints the detraccion legend and bank account when it applies', function () {
    config(['billing.sunat.cert_path' => base_path('tests/Fixtures/certificates/test-certificate.pem')]);

    $this->app->bind(SunatClientInterface::class, fn () => new class implements SunatClientInterface
    {
        public function send(string $xmlSigned, string $documentName): array
        {
            return ['cdr_zip' => 'fake-zip-content', 'codigo' => 0, 'mensaje' => 'Aceptado', 'notas' => []];
        }
    });

    CompanySetting::current()->update(['cuenta_detraccion' => '00-123-456789']);

    $sale = Sale::factory()->create([
        'comprobante_tipo' => 'factura',
        'condicion_pago' => 'contado',
        'subtotal' => 1000,
        'igv' => 180,
        'total' => 1180,
    ]);
    $item = Service::factory()->create(['precio_venta' => 1000]);
    SaleItem::factory()->forService($item)->create([
        'sale_id' => $sale->id,
        'cantidad' => 1,
        'precio_unitario' => 1000,
        'descuento' => 0,
        'subtotal' => 1000,
    ]);

    $document = app(EmitElectronicDocument::class)->handle($sale);

    expect($document->pdf_path)->not->toBeNull()
        ->and(Storage::disk('local')->exists($document->pdf_path))->toBeTrue();

    $html = view('pdf.comprobante', [
        'document' => $document,
        'sale' => $document->sale,
        'company' => CompanySetting::current(),
        'logoBase64' => null,
        'qrBase64' => '',
        'montoEnLetras' => app(NumeroEnLetrasService::class)->convertir((float) $sale->total),
        'bankAccounts' => collect(),
        'detraccion' => app(DetraccionCalculator::class)->calcular((float) $sale->total, true),
    ])->render();

    expect($html)->toContain('Sistema de Pago de Obligaciones Tributarias')
        ->and($html)->toContain('00-123-456789');
});

test('greenter service refuses to build credit or debit notes without persisted amounts', function () {
    $sale = saleWithItem();
    $document = ElectronicDocument::factory()->create([
        'sale_id' => $sale->id,
        'tipo' => 'nota_credito',
    ]);

    expect(fn () => app(GreenterService::class)->buildInvoice($sale, $document))
        ->toThrow(RuntimeException::class);
});

test('issue credit note rejects unsupported originals and creates valid note', function () {
    $sale = Sale::factory()->create();
    $invalidOriginal = ElectronicDocument::factory()->create([
        'sale_id' => $sale->id,
        'tipo' => 'nota_credito',
    ]);
    $validOriginal = ElectronicDocument::factory()->create([
        'sale_id' => $sale->id,
        'tipo' => 'factura',
        'sunat_estado' => 'aceptado',
    ]);

    expect(fn () => app(IssueCreditNote::class)->handle($invalidOriginal, '01', 'Anulación', 10))
        ->toThrow(ValidationException::class);

    $creditNote = app(IssueCreditNote::class)->handle($validOriginal, '01', 'Anulación', 10);

    expect($creditNote->tipo)->toBe('nota_credito')
        ->and($creditNote->cpe_afectado_id)->toBe($validOriginal->id)
        ->and($creditNote->sunat_estado)->toBe('pendiente');
});

test('vendedor user can view billing index', function () {
    $user = vendedorUser();
    ElectronicDocument::factory()->create();

    $this->actingAs($user)
        ->get(route('vendedor.facturacion.index', ['current_team' => $user->currentTeam]))
        ->assertOk();
});

function saleWithItem(): Sale
{
    $sale = Sale::factory()->create([
        'comprobante_tipo' => 'factura',
        'condicion_pago' => 'contado',
        'subtotal' => 100,
        'igv' => 18,
        'total' => 118,
    ]);
    $item = Product::factory()->create(['precio_venta' => 100]);

    SaleItem::factory()->create([
        'sale_id' => $sale->id,
        'product_id' => $item->id,
        'cantidad' => 1,
        'precio_unitario' => 100,
        'descuento' => 0,
        'subtotal' => 100,
    ]);

    return $sale;
}

test('el comprobante agrupa las unidades del mismo producto y precio en una sola linea', function () {
    $sale = Sale::factory()->create([
        'comprobante_tipo' => 'factura',
        'condicion_pago' => 'contado',
        'subtotal' => 490,
        'igv' => 88.2,
        'total' => 578.2,
    ]);
    $extintor = Product::factory()->create();
    $otro = Product::factory()->create();

    SaleItem::factory()->count(6)->create([
        'sale_id' => $sale->id,
        'product_id' => $extintor->id,
        'cantidad' => 1,
        'precio_unitario' => 65,
        'descuento' => 0,
        'subtotal' => 65,
    ]);
    SaleItem::factory()->create([
        'sale_id' => $sale->id,
        'product_id' => $otro->id,
        'cantidad' => 1,
        'precio_unitario' => 100,
        'descuento' => 0,
        'subtotal' => 100,
    ]);
    $document = ElectronicDocument::factory()->create(['sale_id' => $sale->id, 'tipo' => 'factura']);

    $details = app(GreenterService::class)->buildInvoice($sale, $document)->getDetails();

    expect($details)->toHaveCount(2)
        ->and($details[0]->getCodProducto())->toBe($extintor->codigo)
        ->and($details[0]->getCantidad())->toEqual(6.0)
        ->and($details[0]->getMtoPrecioUnitario())->toEqual(65.0)
        ->and(round($details[0]->getMtoValorUnitario(), 4))->toEqual(55.0847)
        ->and($details[0]->getMtoValorVenta())->toEqual(330.51)
        ->and($details[0]->getIgv())->toEqual(59.49)
        ->and($details[1]->getCantidad())->toEqual(1.0);
});

test('la descarga masiva arma un zip con xml cdr y pdf solo de los comprobantes marcados del vendedor', function () {
    Storage::fake('local');
    $user = vendedorUser();
    $marcado = ElectronicDocument::factory()->create([
        'sale_id' => Sale::factory()->create(['vendedor_id' => $user->id])->id,
        'tipo' => 'factura', 'serie' => 'F001', 'correlativo' => 7,
        'xml_path' => 'xml/a.xml', 'cdr_path' => 'cdr/a.zip', 'pdf_path' => 'pdf/a.pdf',
    ]);
    ElectronicDocument::factory()->create([
        'sale_id' => Sale::factory()->create()->id,
        'xml_path' => 'xml/ajeno.xml',
    ]);
    Storage::disk('local')->put('xml/a.xml', '<xml/>');
    Storage::disk('local')->put('cdr/a.zip', 'cdr');
    Storage::disk('local')->put('pdf/a.pdf', '%PDF');
    Storage::disk('local')->put('xml/ajeno.xml', '<ajeno/>');

    $response = $this->actingAs($user)
        ->get(route('vendedor.facturacion.descarga-masiva', ['current_team' => $user->currentTeam, 'ids' => [$marcado->id], 'incluir' => ['xml', 'pdf']]))
        ->assertOk();

    $zip = new PharData($response->baseResponse->getFile()->getPathname());
    $archivos = collect(iterator_to_array(new RecursiveIteratorIterator($zip)))->keys()
        ->map(fn ($ruta) => str($ruta)->after('.zip/')->toString())->sort()->values()->all();

    expect($archivos)->toHaveCount(2)
        ->and($archivos[0])->toStartWith('pdf/F-F001-00000007_')
        ->and($archivos[1])->toStartWith('xml/F-F001-00000007_');
});

test('el registro de ventas en excel lista lo filtrado y la nota de credito resta', function () {
    $user = vendedorUser();
    $sale = Sale::factory()->create(['vendedor_id' => $user->id, 'subtotal' => 67.80, 'igv' => 12.20, 'total' => 80]);
    $factura = ElectronicDocument::factory()->create(['sale_id' => $sale->id, 'tipo' => 'factura', 'serie' => 'F001', 'correlativo' => 65]);
    ElectronicDocument::factory()->create(['sale_id' => $sale->id, 'tipo' => 'nota_credito', 'serie' => 'FC01', 'correlativo' => 1, 'importe' => 80, 'cpe_afectado_id' => $factura->id]);

    $csv = $this->actingAs($user)
        ->get(route('vendedor.facturacion.excel', ['current_team' => $user->currentTeam]))
        ->assertOk()
        ->streamedContent();

    expect($csv)->toContain('F001;00000065')
        ->toContain('67.80;12.20;80.00')
        ->toContain('-67.80;-12.20;-80.00')
        ->toContain('F001-65');
});

test('el buscador y el filtro de notas del listado de facturacion funcionan', function () {
    $user = vendedorUser();
    $sale = Sale::factory()->create(['vendedor_id' => $user->id]);
    ElectronicDocument::factory()->create(['sale_id' => $sale->id, 'tipo' => 'factura', 'serie' => 'F001', 'correlativo' => 65]);
    ElectronicDocument::factory()->create(['sale_id' => $sale->id, 'tipo' => 'nota_credito', 'serie' => 'FC01', 'correlativo' => 1]);
    $team = ['current_team' => $user->currentTeam];

    $this->actingAs($user)->get(route('vendedor.facturacion.index', [...$team, 'buscar' => 'F001-65']))
        ->assertInertia(fn ($page) => $page->where('totalFiltrados', 1));

    $this->actingAs($user)->get(route('vendedor.facturacion.index', [...$team, 'tipo' => 'nota']))
        ->assertInertia(fn ($page) => $page->where('totalFiltrados', 1)->where('documents.data.0.tipo', 'nota_credito'));
});
