<?php

use App\Actions\Certificates\EmitirCertificadosDeVenta;
use App\Actions\Sales\CambiarUnidadVendida;
use App\Actions\Sales\CreateSale;
use App\Models\Certificate;
use App\Models\Client;
use App\Models\InventoryMovement;
use App\Models\InventoryUnit;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Sede;
use App\Models\User;
use Database\Seeders\CertificateTypeSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(CertificateTypeSeeder::class);
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
 * Venta confirmada de 2 extintores con otra unidad del mismo producto libre en el almacén.
 *
 * @return array{sale: Sale, sede: Sede, product: Product, vendidas: list<InventoryUnit>, libre: InventoryUnit}
 */
function ventaParaCambio(User $vendedor): array
{
    $sede = Sede::factory()->almacen()->create();
    $product = Product::factory()->create();
    $unidad = fn (string $serie, string $fabricante) => InventoryUnit::factory()->create([
        'product_id' => $product->id,
        'sede_almacen_id' => $sede->id,
        'numero_serie' => $serie,
        'serie_fabricante' => $fabricante,
        'capacidad' => '6 kg',
        'marca' => 'BUCKEYE',
        'estado' => 'disponible',
    ]);
    $vendidas = [$unidad('BF-EQ-000001', 'FAB-1'), $unidad('BF-EQ-000002', 'FAB-2')];
    $libre = $unidad('BF-EQ-000003', 'FAB-3');

    $sale = app(CreateSale::class)->handle([
        'client_id' => Client::factory()->create()->id,
        'sede_id' => $sede->id,
        'fecha' => now()->toDateString(),
        'destino' => 'local_cliente',
        'condicion_pago' => 'contado',
        'comprobante_tipo' => 'factura',
    ], array_map(fn (InventoryUnit $u) => [
        'tipo_linea' => 'unidad_nueva',
        'numero_serie' => $u->numero_serie,
        'product_id' => $product->id,
        'cantidad' => 1,
        'precio_unitario' => 65,
    ], $vendidas), $vendedor->id);
    $sale->update(['estado' => 'confirmada']);

    return compact('sale', 'sede', 'product', 'vendidas', 'libre');
}

test('cambiar el extintor devuelve la unidad al stock, saca la nueva y corrige el equipo y los certificados', function () {
    $vendedor = vendedorUser();
    ['sale' => $sale, 'vendidas' => $vendidas, 'libre' => $libre] = ventaParaCambio($vendedor);
    $item = $sale->items()->where('inventory_unit_id', $vendidas[0]->id)->firstOrFail();

    $certificados = app(EmitirCertificadosDeVenta::class)->handle($sale->fresh(), [[
        'tipos' => ['operatividad_garantia'],
        'unidades' => $sale->items->map(fn ($i) => ['equipment_id' => $i->equipment_id, 'numero_cliente' => 'EXT-0'.$i->id])->all(),
    ]]);
    $certificado = $certificados->first();
    $numero = $certificado->numero;

    app(CambiarUnidadVendida::class)->handle($sale, $item, 'BF-EQ-000003', $vendedor->id);

    expect($vendidas[0]->fresh()->estado)->toBe('disponible')
        ->and($libre->fresh()->estado)->toBe('vendido')
        ->and($item->fresh()->inventory_unit_id)->toBe($libre->id)
        ->and($item->equipment->fresh()->numero_serie)->toBe('BF-EQ-000003')
        ->and($item->equipment->fresh()->serie_fabricante)->toBe('FAB-3');

    $movimientos = InventoryMovement::where('referencia_id', $sale->id)->where('referencia_type', $sale->getMorphClass())->get();
    expect($movimientos->where('inventory_unit_id', $vendidas[0]->id)->pluck('tipo')->all())->toContain('ingreso')
        ->and($movimientos->where('inventory_unit_id', $libre->id)->pluck('tipo')->all())->toBe(['salida_venta']);

    $corregido = Certificate::find($certificado->id);
    $series = $corregido->certificateUnits()->orderBy('orden')->pluck('numero_serie_snapshot')->all();

    expect($corregido->numero)->toBe($numero)
        ->and($corregido->revision)->toBeGreaterThan($certificado->revision)
        ->and($series)->toContain('FAB-3')
        ->and($series)->not->toContain('FAB-1')
        ->and($series)->toContain('FAB-2')
        ->and($corregido->certificateUnits()->pluck('numero_cliente')->filter()->count())->toBe(2)
        ->and($corregido->revisions()->latest('id')->first()->motivo)->toBe('Serie BF-EQ-000001 cambiada por BF-EQ-000003');
});

test('no se cambia por una unidad de otro producto, ya vendida o de otra sede', function (string $caso) {
    $vendedor = vendedorUser();
    ['sale' => $sale, 'vendidas' => $vendidas, 'libre' => $libre] = ventaParaCambio($vendedor);
    $item = $sale->items()->where('inventory_unit_id', $vendidas[0]->id)->firstOrFail();

    match ($caso) {
        'otro producto' => $libre->update(['product_id' => Product::factory()->create()->id]),
        'ya vendida' => $libre->update(['estado' => 'vendido']),
        'otra sede' => $libre->update(['sede_almacen_id' => Sede::factory()->almacen()->create()->id]),
    };

    expect(fn () => app(CambiarUnidadVendida::class)->handle($sale, $item, $libre->numero_serie, $vendedor->id))
        ->toThrow(ValidationException::class);

    expect($item->fresh()->inventory_unit_id)->toBe($vendidas[0]->id)
        ->and($vendidas[0]->fresh()->estado)->toBe('vendido');
})->with(['otro producto', 'ya vendida', 'otra sede']);

test('cambiar un extintor y volver al original no se pisa en el kardex', function () {
    $vendedor = vendedorUser();
    ['sale' => $sale, 'vendidas' => $vendidas, 'libre' => $libre] = ventaParaCambio($vendedor);
    $item = $sale->items()->where('inventory_unit_id', $vendidas[0]->id)->firstOrFail();

    // U -> U2 ...
    app(CambiarUnidadVendida::class)->handle($sale, $item, $libre->numero_serie, $vendedor->id);
    // ... y U2 -> U: se vuelve al extintor original.
    app(CambiarUnidadVendida::class)->handle($sale, $item->fresh(), $vendidas[0]->numero_serie, $vendedor->id);

    expect($item->fresh()->inventory_unit_id)->toBe($vendidas[0]->id)
        ->and($vendidas[0]->fresh()->estado)->toBe('vendido');

    // El kardex conserva los movimientos de los DOS cambios además de la
    // salida de la venta original. Antes la clave única de idempotencia los
    // confundía con un doble submit y el segundo cambio reventaba.
    $movimientos = InventoryMovement::query()
        ->where('referencia_id', $sale->id)
        ->where('referencia_type', $sale->getMorphClass())
        ->get();

    expect($movimientos->where('inventory_unit_id', $vendidas[0]->id)->pluck('tipo')->sort()->values()->all())
        ->toBe(['ingreso', 'salida_venta', 'salida_venta'])
        // Cada operación deja su propia huella: por eso no se rechazan entre sí...
        ->and($movimientos->where('inventory_unit_id', $vendidas[0]->id)->pluck('idempotencia')->unique()->count())->toBe(3);
});

test('el doble envio del mismo movimiento de kardex se rechaza por su huella', function () {
    $vendedor = vendedorUser();
    ['sale' => $sale, 'vendidas' => $vendidas] = ventaParaCambio($vendedor);

    $original = InventoryMovement::query()
        ->where('referencia_id', $sale->id)
        ->where('inventory_unit_id', $vendidas[0]->id)
        ->firstOrFail();

    // Exactamente el mismo movimiento otra vez: misma huella, así que la base
    // lo corta en vez de duplicar el kardex.
    $clon = $original->replicate();
    $clon->idempotencia = null;

    expect(fn () => $clon->save())->toThrow(Exception::class);
});

test('el vendedor cambia el extintor desde el detalle de la venta', function () {
    $vendedor = vendedorUser();
    ['sale' => $sale, 'vendidas' => $vendidas] = ventaParaCambio($vendedor);
    $item = $sale->items()->where('inventory_unit_id', $vendidas[1]->id)->firstOrFail();

    $this->actingAs($vendedor)
        ->post(route('vendedor.ventas.cambiar-unidad', ['current_team' => $vendedor->currentTeam, 'sale' => $sale, 'item' => $item]), [
            'numero_serie' => 'BF-EQ-000003',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect($item->fresh()->inventoryUnit->numero_serie)->toBe('BF-EQ-000003');
});
