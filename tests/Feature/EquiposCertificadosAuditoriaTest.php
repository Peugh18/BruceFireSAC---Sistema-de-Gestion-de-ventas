<?php

use App\Actions\Certificates\EmitirCertificadoDeServicio;
use App\Actions\Certificates\IssueCertificate;
use App\Actions\Equipment\QuickRegisterEquipment;
use App\Actions\Sales\ConfirmSale;
use App\Actions\Sales\CreateSale;
use App\Actions\Sales\ProcessSaleItem;
use App\Actions\TecnicoPlanta\ExecuteAndCloseServiceOrder;
use App\Models\Certificate;
use App\Models\CertificateType;
use App\Models\Client;
use App\Models\Deficiency;
use App\Models\DeficiencyAuthorization;
use App\Models\Equipment;
use App\Models\InventoryMovement;
use App\Models\InventoryUnit;
use App\Models\Product;
use App\Models\Quote;
use App\Models\QuoteItem;
use App\Models\Sale;
use App\Models\Sede;
use App\Models\Service;
use App\Models\ServiceOrder;
use App\Models\User;
use App\Services\Certificates\CertificateDocumentData;
use App\Services\Certificates\CertificatePdfService;
use Database\Seeders\CertificateTypeSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\View;
use Illuminate\Validation\ValidationException;

test('a service certificate cannot certify a job absent from the sale', function () {
    $this->seed(CertificateTypeSeeder::class);
    $sale = Sale::factory()->create(['estado' => 'confirmada']);
    $tipo = CertificateType::where('codigo', 'luces_emergencia')->firstOrFail();
    expect(fn () => app(EmitirCertificadoDeServicio::class)->handle($sale, $tipo, ['filas' => [['marca' => 'OPALUX']]]))
        ->toThrow(function (ValidationException $e) {
            expect($e->errors())->toHaveKey('tipo');
        });
    expect(Certificate::where('sale_id', $sale->id)->exists())->toBeFalse();
    expect(EmitirCertificadoDeServicio::tiposDeServicio($sale))->toBeEmpty();
});

test('a certificate preserves internal and manufacturer identifiers independently', function () {
    $this->seed(CertificateTypeSeeder::class);
    $client = Client::factory()->create();
    $equipment = Equipment::factory()->create(['client_id' => $client->id, 'numero_serie' => 'BF-EQ-000123', 'serie_fabricante' => 'FAB-987', 'tipo_agente' => 'PQS ABC']);
    $tipo = CertificateType::where('codigo', 'operatividad_garantia')->firstOrFail();
    $certificate = app(IssueCertificate::class)->handle($tipo, $client, [['equipment_id' => $equipment->id]]);
    $equipment->update(['numero_serie' => 'BF-EQ-000999', 'serie_fabricante' => 'FAB-CAMBIADA']);
    $document = app(CertificateDocumentData::class)->desde($certificate->fresh());
    expect($document['filas'][0]['serie'])->toBe('FAB-987')
        ->and($document['filas'][0]['codigo_interno'])->toBe('BF-EQ-000123')
        ->and(array_column($document['tipo']['columnas'], 'clave'))->toContain('serie', 'codigo_interno');
    // generate() ya dibuja el PDF y dompdf libera su árbol: se revisa el
    // HTML que recibe el motor con los mismos datos.
    $datosDeLaVista = [];
    View::composer('pdf.certificado-operatividad', function ($view) use (&$datosDeLaVista): void {
        $datosDeLaVista = $view->getData();
    });
    $pdf = app(CertificatePdfService::class)->generate($certificate->fresh());
    $renderedText = strip_tags(view('pdf.certificado-operatividad', $datosDeLaVista)->render());
    $contenido = $pdf->output();
    expect($renderedText)->toContain('FAB-987', 'BF-EQ-000123')
        ->and($contenido)->toStartWith('%PDF');
});

test('a recharge rejects incompatible agent or capacity before creating a sale item', function (string $agent, string $capacity) {
    $sale = Sale::factory()->create();
    $equipment = Equipment::factory()->create(['client_id' => $sale->client_id, 'tipo_agente' => 'CO2', 'capacidad' => '10 lb']);
    $service = Service::factory()->create(['nombre' => 'Recarga', 'agente' => $agent, 'capacidad' => $capacity]);
    expect(fn () => app(ProcessSaleItem::class)->handle($sale, ['tipo_linea' => 'recarga_servicio', 'numero_serie' => $equipment->numero_serie, 'service_id' => $service->id, 'cantidad' => 1, 'precio_unitario' => 40, 'subtotal' => 40]))->toThrow(ValidationException::class);
    expect($sale->items()->exists())->toBeFalse();
})->with(['wrong agent' => ['pqs', '10 lb'], 'wrong capacity' => ['co2', '6 kg']]);

test('a BF EQ prefix finds products without selecting an arbitrary physical unit', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $warehouse = Sede::factory()->almacen()->create();
    $user = User::factory()->create(['sede_id' => $warehouse->id]);
    $user->assignRole('Vendedor');
    $unit = InventoryUnit::factory()->create(['numero_serie' => 'BF-EQ-000123', 'sede_almacen_id' => $warehouse->id, 'estado' => 'disponible']);
    $this->actingAs($user)->getJson(route('vendedor.catalogo.buscar', ['current_team' => $user->currentTeam, 'sede_id' => $warehouse->id, 'search' => 'BF-EQ-000']))
        ->assertOk()->assertJsonPath('unidad', null)->assertJsonPath('items.0.id', $unit->product_id);
});

test('technical reception does not attach a known incompatible extinguisher', function () {
    $service = Service::factory()->create(['nombre' => 'Recarga PQS 6 Kg', 'agente' => 'pqs', 'capacidad' => '6 kg']);
    $order = ServiceOrder::factory()->create(['service_id' => $service->id]);
    $equipment = Equipment::factory()->create(['client_id' => $order->client_id, 'tipo_agente' => 'CO2', 'capacidad' => '10 lb']);
    expect(fn () => app(QuickRegisterEquipment::class)->execute($order, ['numero_serie' => $equipment->numero_serie]))->toThrow(ValidationException::class);
    expect($order->equipments()->exists())->toBeFalse();
});

test('billing an authorized replacement keeps physical consumption in the technical order', function () {
    $sale = Sale::factory()->create();
    $order = ServiceOrder::factory()->create(['client_id' => $sale->client_id, 'sede_id' => $sale->sede_id]);
    $deficiency = Deficiency::factory()->create(['service_order_id' => $order->id, 'estado' => 'resuelta']);
    $product = Product::factory()->create(['serializado' => false]);
    $quote = Quote::factory()->create(['client_id' => $sale->client_id, 'sede_id' => $sale->sede_id]);
    QuoteItem::factory()->create(['quote_id' => $quote->id, 'deficiency_id' => $deficiency->id, 'product_id' => $product->id]);
    DeficiencyAuthorization::factory()->create(['deficiency_id' => $deficiency->id, 'cotizacion_adicional_id' => $quote->id]);
    $item = app(ProcessSaleItem::class)->handle($sale, ['tipo_linea' => 'producto', 'product_id' => $product->id, 'deficiency_id' => $deficiency->id, 'cantidad' => 1, 'precio_unitario' => 40, 'subtotal' => 40]);
    expect($item->deficiency_id)->toBe($deficiency->id)
        ->and(InventoryMovement::where('referencia_type', $sale->getMorphClass())->where('referencia_id', $sale->id)->exists())->toBeFalse();
});

test('a recharge accepts equivalent known units without changing stock', function () {
    $sale = Sale::factory()->create();
    $equipment = Equipment::factory()->create(['client_id' => $sale->client_id, 'tipo_agente' => 'PQS ABC', 'capacidad' => '6,0 kgs']);
    $service = Service::factory()->create(['nombre' => 'Recarga PQS 6 KG', 'agente' => 'pqs', 'capacidad' => '6 kg']);
    $item = app(ProcessSaleItem::class)->handle($sale, ['tipo_linea' => 'recarga_servicio', 'numero_serie' => $equipment->numero_serie, 'service_id' => $service->id, 'cantidad' => 1, 'precio_unitario' => 40, 'subtotal' => 40]);
    expect($item->equipment_id)->toBe($equipment->id)
        ->and(InventoryMovement::count())->toBe(0);
});

test('a seller cannot open a service certificate form for unrelated work', function () {
    $this->seed(CertificateTypeSeeder::class);
    $this->seed(RolesAndPermissionsSeeder::class);
    $warehouse = Sede::factory()->almacen()->create();
    $user = User::factory()->create(['sede_id' => $warehouse->id]);
    $user->assignRole('Vendedor');
    $sale = Sale::factory()->create(['estado' => 'confirmada', 'sede_id' => $warehouse->id, 'vendedor_id' => $user->id]);
    $this->actingAs($user)->get(route('vendedor.ventas.certificado-servicio.create', ['current_team' => $user->currentTeam, 'sale' => $sale, 'tipo' => 'luces_emergencia']))->assertNotFound();
});

test('technical consumption rejects a replacement differing from the authorized quote', function (bool $differentProduct, int $quantity) {
    $warehouse = Sede::factory()->almacen()->create();
    $order = ServiceOrder::factory()->create(['sede_id' => $warehouse->id]);
    $deficiency = Deficiency::factory()->create(['service_order_id' => $order->id, 'estado' => 'autorizada']);
    $quoted = Product::factory()->create(['serializado' => false]);
    $used = $differentProduct ? Product::factory()->create(['serializado' => false]) : $quoted;
    $quote = Quote::factory()->create(['client_id' => $order->client_id, 'sede_id' => $order->sede_id]);
    QuoteItem::factory()->create(['quote_id' => $quote->id, 'deficiency_id' => $deficiency->id, 'product_id' => $quoted->id, 'cantidad' => 1]);
    DeficiencyAuthorization::factory()->create(['deficiency_id' => $deficiency->id, 'cotizacion_adicional_id' => $quote->id]);
    InventoryMovement::create(['product_id' => $used->id, 'sede_id' => $warehouse->id, 'cantidad' => 5, 'tipo' => 'ingreso']);
    expect(fn () => app(ExecuteAndCloseServiceOrder::class)->consumeSparePart($order, $deficiency, $used, $quantity, User::factory()->create()))->toThrow(ValidationException::class);
    expect($deficiency->fresh()->estado)->toBe('autorizada')
        ->and(InventoryMovement::where('referencia_type', Deficiency::class)->where('referencia_id', $deficiency->id)->exists())->toBeFalse();
})->with(['wrong product' => [true, 1], 'wrong quantity' => [false, 2]]);

test('an unknown manufacturer serial is not replaced by the internal equipment code', function () {
    $this->seed(CertificateTypeSeeder::class);
    $equipment = Equipment::factory()->create(['numero_serie' => 'BF-EQ-000124', 'serie_fabricante' => null]);
    $type = CertificateType::where('codigo', 'operatividad_garantia')->firstOrFail();
    $certificate = app(IssueCertificate::class)->handle($type, $equipment->client, [['equipment_id' => $equipment->id]]);
    $document = app(CertificateDocumentData::class)->desde($certificate);
    expect($document['filas'][0]['serie'])->toBe('')
        ->and($document['filas'][0]['codigo_interno'])->toBe('BF-EQ-000124');
});

test('nine scanned extinguishers keep their identities in a certificate and public QR', function (string $destination) {
    $this->seed(CertificateTypeSeeder::class);
    $this->seed(RolesAndPermissionsSeeder::class);
    $warehouse = Sede::factory()->almacen()->create();
    $user = User::factory()->create(['sede_id' => $warehouse->id]);
    $user->assignRole('Vendedor');
    $product = Product::factory()->create(['serializado' => true, 'agente' => 'pqs', 'capacidad' => '6 kg']);
    $client = Client::factory()->create();
    $lines = [];
    $internals = [];
    $manufacturers = [];
    for ($i = 9; $i >= 1; $i--) {
        $internal = 'BF-EQ-'.str_pad((string) $i, 6, '0', STR_PAD_LEFT);
        $manufacturer = 'FAB-'.str_pad((string) $i, 4, '0', STR_PAD_LEFT);
        InventoryUnit::factory()->create(['product_id' => $product->id, 'sede_almacen_id' => $warehouse->id, 'numero_serie' => $internal, 'serie_fabricante' => $manufacturer, 'marca' => 'BRUCE', 'anio_fabricacion' => '2026', 'capacidad' => '6 kg', 'estado' => 'disponible']);
        $scan = $this->actingAs($user)->getJson(route('vendedor.catalogo.buscar', ['current_team' => $user->currentTeam, 'sede_id' => $warehouse->id, 'search' => $internal]))->assertOk()->assertJsonPath('unidad.numero_serie', $internal)->assertJsonPath('unidad.serie_fabricante', $manufacturer);
        $lines[] = ['tipo_linea' => 'unidad_nueva', 'numero_serie' => $scan->json('unidad.numero_serie'), 'product_id' => $scan->json('unidad.product_id'), 'cantidad' => 1, 'precio_unitario' => 100];
        $internals[] = $internal;
        $manufacturers[] = $manufacturer;
    }
    $sale = app(CreateSale::class)->handle(['client_id' => $client->id, 'sede_id' => $warehouse->id, 'fecha' => today()->toDateString(), 'destino' => $destination, 'referencia' => $destination === 'vehiculo' ? 'PLACA: ABC-123' : 'LOCAL: Central', 'condicion_pago' => 'credito', 'comprobante_tipo' => 'nota_venta'], $lines, $user->id);
    app(ConfirmSale::class)->handle($sale);
    $certificate = Certificate::with('certificateUnits')->where('sale_id', $sale->id)->sole();
    expect($certificate->certificateUnits->sortBy('orden')->pluck('codigo_interno')->all())->toBe($internals)
        ->and($certificate->certificateUnits->sortBy('orden')->pluck('numero_serie_snapshot')->all())->toBe($manufacturers)
        ->and($certificate->certificateType->codigo)->toBe('operatividad_garantia')
        ->and($certificate->qr_token)->not->toBeIn($internals)
        ->and(InventoryUnit::where('estado', 'vendido')->count())->toBe(9);
    $this->get(route('certificados.verificar', ['token' => $certificate->qr_token]))->assertOk()->assertInertia(fn ($page) => $page->has('certificado.equipos', 9)->where('certificado.equipos.0.serie', 'FAB-0009'));
    $document = app(CertificateDocumentData::class)->desde($certificate);
    expect($document['filas'][0])->toMatchArray(['marca' => 'BRUCE', 'tipo' => 'PQS ABC', 'anio' => '2026', 'codigo_interno' => 'BF-EQ-000009', 'serie' => 'FAB-0009']);
})->with(['vehicle' => 'vehiculo', 'local' => 'local_cliente']);
