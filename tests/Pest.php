<?php

use App\Actions\Billing\IssueCreditNote;
use App\Actions\Sales\CreateSale;
use App\Models\Client;
use App\Models\ElectronicDocument;
use App\Models\InventoryUnit;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Sede;
use App\Models\User;
use App\Services\Billing\GreenterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

if (! function_exists('vendedorUser')) {
    function vendedorUser(): User
    {
        $user = User::factory()->create();
        $user->assignRole('Vendedor');

        return $user;
    }
}

if (! function_exists('ventaConFactura')) {
    /**
     * Venta confirmada de una unidad nueva con su factura/boleta aceptada por SUNAT.
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

        guardarXmlOriginalDePrueba($documento);

        return [$sale->refresh(), $documento, $unit];
    }
}

if (! function_exists('aceptarNota')) {
    /**
     * SUNAT acepta la nota: recién ahí una anulación total anula la venta.
     */
    function aceptarNota(ElectronicDocument $nota): ElectronicDocument
    {
        $nota->update(['sunat_estado' => 'aceptado']);
        app(IssueCreditNote::class)->aplicarSiFueAceptada($nota);

        return $nota;
    }
}

function guardarXmlOriginalDePrueba(ElectronicDocument $documento): void
{
    config(['billing.sunat.cert_path' => base_path('tests/Fixtures/certificates/test-certificate.pem')]);
    $service = app(GreenterService::class);
    $xml = $service->sign($service->buildInvoice($documento->sale, $documento));
    $path = "xml/prueba-{$documento->id}.xml";
    Storage::disk('local')->put($path, $xml);
    $documento->update(['xml_path' => $path]);
}
