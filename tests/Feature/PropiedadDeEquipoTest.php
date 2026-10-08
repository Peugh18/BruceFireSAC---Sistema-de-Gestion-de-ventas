<?php

use App\Actions\Sales\CreateSale;
use App\Models\Certificate;
use App\Models\CertificateUnit;
use App\Models\Client;
use App\Models\Equipment;
use App\Models\InventoryUnit;
use App\Models\Product;
use App\Models\Sede;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->sede = Sede::factory()->almacen()->create();
    $this->product = Product::factory()->create(['precio_venta' => 100]);
    $this->unit = InventoryUnit::factory()->create([
        'product_id' => $this->product->id,
        'sede_almacen_id' => $this->sede->id,
        'numero_serie' => 'BF-PROPIA-001',
        'estado' => 'disponible',
    ]);
});

/**
 * Arma la venta de una serie concreta para el cliente indicado.
 */
function venderSerie(Client $client, Sede $sede, Product $product, string $serie): mixed
{
    return app(CreateSale::class)->handle([
        'client_id' => $client->id,
        'sede_id' => $sede->id,
        'fecha' => now()->toDateString(),
        'destino' => 'local_cliente',
        'condicion_pago' => 'contado',
        'comprobante_tipo' => 'factura',
    ], [
        [
            'tipo_linea' => 'unidad_nueva',
            'numero_serie' => $serie,
            'product_id' => $product->id,
            'cantidad' => 1,
            'precio_unitario' => 100,
            'descuento' => 0,
        ],
    ], vendedorUser()->id);
}

test('una serie con historial vivo de otro cliente no se vende a un cliente distinto', function () {
    $clienteAnterior = Client::factory()->create();
    $clienteNuevo = Client::factory()->create();

    // La serie ya pertenece a otro cliente, que conserva un certificado vigente.
    $equipo = Equipment::factory()->create([
        'client_id' => $clienteAnterior->id,
        'product_id' => $this->product->id,
        'numero_serie' => 'BF-PROPIA-001',
        'estado' => 'activo',
    ]);
    $certificado = Certificate::factory()->create([
        'client_id' => $clienteAnterior->id,
        'estado' => 'vigente',
    ]);
    CertificateUnit::factory()->create([
        'certificate_id' => $certificado->id,
        'equipment_id' => $equipo->id,
    ]);

    // Antes la ficha se reasignaba en silencio y el certificado del cliente
    // anterior quedaba colgando del equipo del nuevo.
    expect(fn () => venderSerie($clienteNuevo, $this->sede, $this->product, 'BF-PROPIA-001'))
        ->toThrow(ValidationException::class);

    // La ficha sigue siendo del cliente anterior.
    expect($equipo->refresh()->client_id)->toBe($clienteAnterior->id);
});

test('una serie cuyo historial anterior quedo cerrado se reutiliza para el nuevo cliente', function () {
    $clienteAnterior = Client::factory()->create();
    $clienteNuevo = Client::factory()->create();

    // Es lo que deja RevertSale al anular la venta: equipo de baja y
    // certificados anulados. Esa propiedad anterior ya no tiene historial vivo.
    $equipo = Equipment::factory()->create([
        'client_id' => $clienteAnterior->id,
        'product_id' => $this->product->id,
        'numero_serie' => 'BF-PROPIA-001',
        'estado' => 'baja',
    ]);
    $certificado = Certificate::factory()->create([
        'client_id' => $clienteAnterior->id,
        'estado' => 'anulado',
    ]);
    CertificateUnit::factory()->create([
        'certificate_id' => $certificado->id,
        'equipment_id' => $equipo->id,
    ]);

    $sale = venderSerie($clienteNuevo, $this->sede, $this->product, 'BF-PROPIA-001');

    // La ficha se reutiliza para el nuevo comprador, sin arrastrar nada ajeno.
    expect($equipo->refresh()->client_id)->toBe($clienteNuevo->id)
        ->and($equipo->refresh()->estado)->toBe('activo')
        // El certificado anulado del cliente anterior sigue siendo suyo.
        ->and($certificado->refresh()->client_id)->toBe($clienteAnterior->id)
        ->and($sale->total)->toEqual(100.0);
});
