<?php

use App\Actions\Sales\ConfirmSale;
use App\Actions\Sales\CreateSale;
use App\Actions\Sales\DescartarVentaSinComprobante;
use App\Models\Certificate;
use App\Models\Client;
use App\Models\InventoryUnit;
use App\Models\Product;
use App\Models\Quote;
use App\Models\Sale;
use App\Models\Sede;
use App\Models\User;
use Database\Seeders\CertificateTypeSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(CertificateTypeSeeder::class);

    // Al confirmar una factura se firma el XML: se usa el certificado de prueba del proyecto.
    config(['billing.sunat.cert_path' => base_path('tests/Fixtures/certificates/test-certificate.pem')]);
});

if (! function_exists('vendedorUser')) {
    function vendedorUser(): User
    {
        $user = User::factory()->create();
        $user->assignRole('Vendedor');

        return $user;
    }
}

/**
 * @return array{0: Sale, 1: InventoryUnit}
 */
function borradorConUnidad(string $comprobante = 'boleta', ?int $quoteId = null, ?Client $client = null): array
{
    $sede = Sede::factory()->almacen()->create();
    $unidad = InventoryUnit::factory()->create([
        'product_id' => Product::factory()->create()->id,
        'sede_almacen_id' => $sede->id,
        'estado' => 'disponible',
    ]);

    $sale = app(CreateSale::class)->handle([
        'client_id' => ($client ?? Client::factory()->dni()->create())->id,
        'sede_id' => $sede->id,
        'quote_id' => $quoteId,
        'fecha' => now()->toDateString(),
        'destino' => 'local_cliente',
        'condicion_pago' => 'contado',
        'comprobante_tipo' => $comprobante,
    ], [[
        'tipo_linea' => 'unidad_nueva',
        'numero_serie' => $unidad->numero_serie,
        'product_id' => $unidad->product_id,
        'cantidad' => 1,
        'precio_unitario' => 65,
    ]], vendedorUser()->id);

    return [$sale, $unidad];
}

test('descartar un borrador libera sus unidades y devuelve la cotizacion a aceptada', function () {
    $client = Client::factory()->dni()->create();
    $quote = Quote::factory()->aceptada()->create(['client_id' => $client->id]);
    [$sale, $unidad] = borradorConUnidad(quoteId: $quote->id, client: $client);

    expect($unidad->fresh()->estado)->toBe('vendido')
        ->and($quote->fresh()->estado)->toBe('convertida');

    app(DescartarVentaSinComprobante::class)->handle($sale);

    expect($sale->fresh()->estado)->toBe('anulada')
        ->and($unidad->fresh()->estado)->toBe('disponible')
        ->and($quote->fresh()->estado)->toBe('aceptada');
});

test('una nota de venta confirmada se anula y sus certificados quedan anulados', function () {
    [$sale, $unidad] = borradorConUnidad('nota_venta');
    app(ConfirmSale::class)->handle($sale);

    expect(Certificate::where('sale_id', $sale->id)->where('estado', 'vigente')->count())->toBeGreaterThan(0);

    app(DescartarVentaSinComprobante::class)->handle($sale->fresh());

    expect($sale->fresh()->estado)->toBe('anulada')
        ->and($unidad->fresh()->estado)->toBe('disponible')
        ->and(Certificate::where('sale_id', $sale->id)->where('estado', 'vigente')->count())->toBe(0);
});

test('una factura o boleta confirmada no se descarta: se corrige con editar comprobante o nota de credito', function () {
    [$sale] = borradorConUnidad('boleta');
    app(ConfirmSale::class)->handle($sale);

    expect(fn () => app(DescartarVentaSinComprobante::class)->handle($sale->fresh()))
        ->toThrow(ValidationException::class, 'nota de crédito');
});

test('la limpieza diaria descarta solo los borradores olvidados', function () {
    [$viejo, $unidadVieja] = borradorConUnidad();
    [$reciente] = borradorConUnidad();
    Sale::whereKey($viejo->id)->update(['updated_at' => now()->subDays(8)]);

    $this->artisan('ventas:descartar-borradores')->assertSuccessful();

    expect($viejo->fresh()->estado)->toBe('anulada')
        ->and($unidadVieja->fresh()->estado)->toBe('disponible')
        ->and($reciente->fresh()->estado)->toBe('borrador');
});

test('el vendedor descarta el borrador desde el detalle', function () {
    $vendedor = vendedorUser();
    [$sale] = borradorConUnidad();

    $this->actingAs($vendedor)
        ->post(route('vendedor.ventas.descartar', ['current_team' => $vendedor->currentTeam, 'sale' => $sale]))
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect($sale->fresh()->estado)->toBe('anulada');
});
