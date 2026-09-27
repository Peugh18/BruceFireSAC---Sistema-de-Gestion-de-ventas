<?php

use App\Actions\Sales\CreateSale;
use App\Actions\Sales\RevertSale;
use App\Models\Client;
use App\Models\ElectronicDocument;
use App\Models\InventoryMovement;
use App\Models\InventoryUnit;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Sede;
use App\Models\Service;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\Billing\GreenterService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
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
 * @param  list<array<string, mixed>>  $items
 * @param  array<string, mixed>  $extra
 */
function crearVenta(array $items, array $extra = [], ?Sede $sede = null): Sale
{
    return app(CreateSale::class)->handle([
        'client_id' => Client::factory()->create()->id,
        'sede_id' => ($sede ?? Sede::factory()->almacen()->create())->id,
        'fecha' => '2026-09-19',
        'destino' => 'local_cliente',
        'condicion_pago' => 'contado',
        'comprobante_tipo' => 'factura',
        ...$extra,
    ], $items, vendedorUser()->id);
}

function productoConStock(Sede $sede, int $stock): Product
{
    $product = Product::factory()->serializado(false)->create(['nombre' => 'BASE PARA EXTINTOR 6 KG']);
    InventoryMovement::create([
        'product_id' => $product->id,
        'sede_id' => $sede->id,
        'tipo' => 'ingreso',
        'cantidad' => $stock,
    ]);

    return $product;
}

test('los precios incluyen IGV como en la factura real de 80 soles', function () {
    $service = Service::factory()->create();

    $sale = crearVenta([
        ['tipo_linea' => 'servicio', 'service_id' => $service->id, 'cantidad' => 1, 'precio_unitario' => 65],
        ['tipo_linea' => 'servicio', 'service_id' => $service->id, 'cantidad' => 1, 'precio_unitario' => 15],
    ]);

    expect((float) $sale->total)->toEqual(80.0)
        ->and((float) $sale->subtotal + (float) $sale->igv)->toEqual(80.0)
        ->and(round((float) $sale->igv, 2))->toEqual(12.21);
});

test('un producto sin serie se vende por cantidad y descuenta su stock', function () {
    $sede = Sede::factory()->almacen()->create();
    $product = productoConStock($sede, 10);

    $sale = crearVenta([
        ['tipo_linea' => 'producto', 'product_id' => $product->id, 'cantidad' => 3, 'precio_unitario' => 65],
    ], sede: $sede);

    expect($sale->items->first()->tipo_linea)->toBe('producto')
        ->and((int) InventoryMovement::where('product_id', $product->id)->sum('cantidad'))->toBe(7);

    app(RevertSale::class)->handle($sale);

    expect((int) InventoryMovement::where('product_id', $product->id)->sum('cantidad'))->toBe(10);
});

test('no se vende mas cantidad de la que hay en stock', function () {
    $sede = Sede::factory()->almacen()->create();
    $product = productoConStock($sede, 2);

    expect(fn () => crearVenta([
        ['tipo_linea' => 'producto', 'product_id' => $product->id, 'cantidad' => 5, 'precio_unitario' => 65],
    ], sede: $sede))->toThrow(ValidationException::class, 'Stock insuficiente');

    expect(Sale::count())->toBe(0);
});

test('credito con cuotas libres crea cada cuota y la factura las lleva', function () {
    $service = Service::factory()->create();

    $sale = crearVenta(
        [['tipo_linea' => 'servicio', 'service_id' => $service->id, 'cantidad' => 7, 'precio_unitario' => 35]],
        [
            'condicion_pago' => 'credito',
            'cuotas' => [
                ['fecha_vencimiento' => '2026-10-19', 'monto' => 145],
                ['fecha_vencimiento' => '2026-09-26', 'monto' => 100],
            ],
        ],
    );

    $cuotas = $sale->installments()->orderBy('numero_cuota')->get();

    expect($cuotas)->toHaveCount(2)
        ->and($cuotas[0]->fecha_vencimiento->toDateString())->toBe('2026-09-26')
        ->and((float) $cuotas[0]->monto)->toEqual(100.0)
        ->and($sale->fresh()->diasCredito())->toBe(30);

    $document = ElectronicDocument::factory()->create(['sale_id' => $sale->id, 'tipo' => 'factura']);
    $invoice = app(GreenterService::class)->buildInvoice($sale->fresh(), $document);

    expect($invoice->getCuotas())->toHaveCount(2)
        ->and($invoice->getFormaPago()->getTipo())->toBe('Credito');
});

test('las cuotas deben sumar exactamente el total', function () {
    $service = Service::factory()->create();

    expect(fn () => crearVenta(
        [['tipo_linea' => 'servicio', 'service_id' => $service->id, 'cantidad' => 1, 'precio_unitario' => 100]],
        ['condicion_pago' => 'credito', 'cuotas' => [['fecha_vencimiento' => '2026-10-01', 'monto' => 90]]],
    ))->toThrow(ValidationException::class, 'debe ser igual al total');
});

test('la referencia se guarda y viaja a SUNAT como observacion', function () {
    $service = Service::factory()->create();

    $sale = crearVenta(
        [['tipo_linea' => 'servicio', 'service_id' => $service->id, 'cantidad' => 1, 'precio_unitario' => 80]],
        ['destino' => 'vehiculo', 'referencia' => 'PLACA: AVR-833'],
    );

    $document = ElectronicDocument::factory()->create(['sale_id' => $sale->id, 'tipo' => 'factura']);

    expect($sale->referencia)->toBe('PLACA: AVR-833')
        ->and(app(GreenterService::class)->buildInvoice($sale, $document)->getObservacion())->toContain('PLACA: AVR-833');
});

test('el buscador del catalogo encuentra por nombre, por codigo y una serie exacta con su stock', function () {
    $user = vendedorUser();
    $sede = Sede::factory()->almacen()->create();
    $base = productoConStock($sede, 4);
    $extintor = Product::factory()->create(['nombre' => 'EXTINTOR PQS 6KG', 'codigo' => 'EXT-6KG']);
    InventoryUnit::factory()->create([
        'product_id' => $extintor->id,
        'sede_almacen_id' => $sede->id,
        'numero_serie' => 'BF-EQ-000099',
        'estado' => 'disponible',
    ]);
    $team = ['current_team' => $user->currentTeam];

    $this->actingAs($user)
        ->getJson(route('vendedor.catalogo.buscar', [...$team, 'search' => 'extintor base', 'sede_id' => $sede->id]))
        ->assertOk()
        ->assertJsonPath('items.0.id', $base->id)
        ->assertJsonPath('items.0.stock', 4);

    $this->actingAs($user)
        ->getJson(route('vendedor.catalogo.buscar', [...$team, 'search' => 'EXT-6KG', 'sede_id' => $sede->id]))
        ->assertJsonPath('items.0.id', $extintor->id)
        ->assertJsonPath('items.0.stock', 1);

    $this->actingAs($user)
        ->getJson(route('vendedor.catalogo.buscar', [...$team, 'search' => 'BF-EQ-000099', 'sede_id' => $sede->id]))
        ->assertJsonPath('unidad.numero_serie', 'BF-EQ-000099');

    $this->actingAs($user)
        ->getJson(route('vendedor.catalogo.unidades', [...$team, 'product_id' => $extintor->id, 'sede_id' => $sede->id]))
        ->assertJsonCount(1)
        ->assertJsonPath('0.numero_serie', 'BF-EQ-000099');
});

test('la ficha del cliente trae sus placas para la referencia', function () {
    $user = vendedorUser();
    $client = Client::factory()->create();
    Vehicle::factory()->create(['client_id' => $client->id, 'placa' => 'AVR-833']);

    $this->actingAs($user)
        ->getJson(route('vendedor.clientes.ficha', ['current_team' => $user->currentTeam, 'client' => $client]))
        ->assertOk()
        ->assertJsonPath('razon_social', $client->razon_social)
        ->assertJsonPath('vehiculos.0.placa', 'AVR-833');
});
