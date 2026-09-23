<?php

use App\Actions\Billing\EmitElectronicDocument;
use App\Actions\Sales\ConfirmSale;
use App\Actions\Sales\CreateSale;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\DocumentSeries;
use App\Models\ElectronicDocument;
use App\Models\InventoryUnit;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Sede;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

/**
 * @return array{0: Sale, 1: InventoryUnit}
 */
function crearNotaVenta(array $cliente = [], string $condicionPago = 'contado'): array
{
    $vendedor = vendedorUser();
    $client = Client::factory()->create($cliente);
    $sede = Sede::factory()->almacen()->create();
    $product = Product::factory()->create(['precio_venta' => 100]);
    $unit = InventoryUnit::factory()->create([
        'product_id' => $product->id,
        'sede_almacen_id' => $sede->id,
        'estado' => 'disponible',
    ]);

    $sale = app(CreateSale::class)->handle([
        'client_id' => $client->id,
        'sede_id' => $sede->id,
        'fecha' => now()->toDateString(),
        'destino' => 'local_cliente',
        'condicion_pago' => $condicionPago,
        'comprobante_tipo' => 'nota_venta',
    ], [[
        'tipo_linea' => 'unidad_nueva',
        'numero_serie' => $unit->numero_serie,
        'product_id' => $product->id,
        'cantidad' => 1,
        'precio_unitario' => 100,
    ]], $vendedor->id);

    return [$sale, $unit];
}

test('confirmar una nota de venta no emite comprobante ni llama a SUNAT', function () {
    Http::fake();
    [$sale] = crearNotaVenta();

    $confirmada = app(ConfirmSale::class)->handle($sale);

    expect($confirmada->estado)->toBe('confirmada')
        ->and($confirmada->numero_nota_venta)->toBe('NV-0001')
        ->and(ElectronicDocument::count())->toBe(0)
        ->and(DocumentSeries::where('tipo_comprobante', 'factura')->count())->toBe(0)
        ->and(DocumentSeries::where('tipo_comprobante', 'boleta')->count())->toBe(0);

    Http::assertNothingSent();
    expect(AuditLog::where('action', 'venta.nota_venta_confirmada')->exists())->toBeTrue();
});

test('la nota de venta descuenta stock y genera cuotas igual que una venta normal', function () {
    [$sale, $unit] = crearNotaVenta(condicionPago: 'credito_30');

    expect($unit->refresh()->estado)->toBe('vendido')
        ->and($sale->installments()->count())->toBe(1);
});

test('la numeración de notas de venta es correlativa y propia', function () {
    [$primera] = crearNotaVenta();
    [$segunda] = crearNotaVenta();

    expect(app(ConfirmSale::class)->handle($primera)->numero_nota_venta)->toBe('NV-0001')
        ->and(app(ConfirmSale::class)->handle($segunda)->numero_nota_venta)->toBe('NV-0002')
        ->and(DocumentSeries::where('tipo_comprobante', 'nota_venta')->value('serie'))->toBe('NV01');
});

test('una nota de venta no exige que el RUC del cliente esté activo y habido', function () {
    [$sale] = crearNotaVenta([
        'tipo_documento' => 'ruc',
        'numero_documento' => '20123456789',
        'estado_contribuyente' => 'BAJA',
        'condicion_domicilio' => 'NO HABIDO',
    ]);

    expect(app(ConfirmSale::class)->handle($sale)->estado)->toBe('confirmada');
});

test('emitir un documento electrónico para una nota de venta lanza excepción', function () {
    [$sale] = crearNotaVenta();

    expect(fn () => app(EmitElectronicDocument::class)->handle($sale))
        ->toThrow(InvalidArgumentException::class);
});

test('el vendedor registra una nota de venta desde el formulario y descarga su pdf', function () {
    $vendedor = vendedorUser();
    $client = Client::factory()->create();
    $sede = Sede::factory()->almacen()->create();
    $product = Product::factory()->create(['precio_venta' => 100]);
    $unit = InventoryUnit::factory()->create([
        'product_id' => $product->id,
        'sede_almacen_id' => $sede->id,
        'estado' => 'disponible',
    ]);
    $team = ['current_team' => $vendedor->currentTeam];

    $this->actingAs($vendedor)
        ->post(route('vendedor.ventas.store', $team), [
            'client_id' => $client->id,
            'sede_id' => $sede->id,
            'fecha' => now()->toDateString(),
            'destino' => 'local_cliente',
            'condicion_pago' => 'contado',
            'comprobante_tipo' => 'nota_venta',
            'items' => [[
                'tipo_linea' => 'unidad_nueva',
                'numero_serie' => $unit->numero_serie,
                'product_id' => $product->id,
                'cantidad' => 1,
                'precio_unitario' => 100,
            ]],
        ])
        ->assertRedirect();

    $sale = Sale::firstOrFail();

    $this->actingAs($vendedor)
        ->get(route('vendedor.ventas.nota-venta-pdf', [...$team, 'sale' => $sale]))
        ->assertNotFound();

    $this->actingAs($vendedor)
        ->post(route('vendedor.ventas.confirmar', [...$team, 'sale' => $sale]))
        ->assertRedirect();

    $this->actingAs($vendedor)
        ->get(route('vendedor.ventas.nota-venta-pdf', [...$team, 'sale' => $sale->fresh()]))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

test('el listado de ventas separa notas de venta de las ventas con comprobante', function () {
    $vendedor = vendedorUser();
    Sale::factory()->create(['comprobante_tipo' => 'nota_venta', 'vendedor_id' => $vendedor->id]);
    Sale::factory()->create(['comprobante_tipo' => 'factura', 'vendedor_id' => $vendedor->id]);
    $url = route('vendedor.ventas.index', ['current_team' => $vendedor->currentTeam]);

    $this->actingAs($vendedor)->get($url.'?comprobante=nota_venta')
        ->assertInertia(fn ($page) => $page->has('sales.data', 1)->where('sales.data.0.comprobante_tipo', 'nota_venta'));

    $this->actingAs($vendedor)->get($url.'?comprobante=sunat')
        ->assertInertia(fn ($page) => $page->has('sales.data', 1)->where('sales.data.0.comprobante_tipo', 'factura'));
});
