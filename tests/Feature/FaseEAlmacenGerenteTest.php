<?php

use App\Models\Installment;
use App\Models\InventoryMovement;
use App\Models\InventoryUnit;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Sale;
use App\Models\SalePayment;
use App\Models\Sede;
use App\Models\Service;
use App\Models\User;
use App\Services\Billing\GreenterService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function gerenteFaseE(): User
{
    $gerente = User::factory()->create();
    $gerente->assignRole('Gerente');

    return $gerente;
}

function productoJson(array $extra = []): array
{
    return [
        'codigo' => 'PRD-E-1', 'nombre' => 'Producto E', 'unidad_medida' => 'NIU',
        'precio_venta' => 10, 'aplica_igv' => true, 'serializado' => false, 'activo' => true,
        ...$extra,
    ];
}

// A2 categorías

test('las seis categorias de siempre quedan migradas y solo extintor genera alertas', function () {
    expect(ProductCategory::count())->toBeGreaterThanOrEqual(6)
        ->and(ProductCategory::clavesConAlertaDeVencimiento())->toBe(['extintor']);
});

test('el gerente crea, renombra y desactiva categorias y solo borra la que nadie usa', function () {
    $gerente = gerenteFaseE();
    $team = ['current_team' => $gerente->currentTeam];

    $this->actingAs($gerente)->post(route('gerente.categorias.store', $team), [
        'nombre' => 'Mangueras', 'genera_alertas_vencimiento' => true,
    ])->assertSessionHasNoErrors();
    $categoria = ProductCategory::where('clave', 'mangueras')->sole();
    expect($categoria->genera_alertas_vencimiento)->toBeTrue()
        ->and(ProductCategory::clavesConAlertaDeVencimiento())->toContain('mangueras');

    $this->actingAs($gerente)->put(route('gerente.categorias.update', [...$team, 'categoria' => $categoria->id]), [
        'nombre' => 'Mangueras y accesorios', 'genera_alertas_vencimiento' => false, 'activo' => true,
    ])->assertSessionHasNoErrors();
    expect($categoria->refresh()->nombre)->toBe('Mangueras y accesorios')
        ->and($categoria->clave)->toBe('mangueras');

    Product::factory()->create(['categoria' => 'mangueras']);
    $this->actingAs($gerente)->delete(route('gerente.categorias.destroy', [...$team, 'categoria' => $categoria->id]))
        ->assertSessionHasErrors('categoria');
    expect(ProductCategory::where('clave', 'mangueras')->exists())->toBeTrue();

    $libre = ProductCategory::factory()->create();
    $this->actingAs($gerente)->delete(route('gerente.categorias.destroy', [...$team, 'categoria' => $libre->id]))
        ->assertSessionHasNoErrors();
    expect(ProductCategory::whereKey($libre->id)->exists())->toBeFalse();
});

test('un vendedor no gestiona categorias', function () {
    $vendedor = User::factory()->create();
    $vendedor->assignRole('Vendedor');

    $this->actingAs($vendedor)->post(route('gerente.categorias.store', ['current_team' => $vendedor->currentTeam]), ['nombre' => 'X'])
        ->assertForbidden();
});

test('productos y servicios solo aceptan categorias activas', function () {
    $gerente = gerenteFaseE();
    $team = ['current_team' => $gerente->currentTeam];
    ProductCategory::factory()->create(['clave' => 'vieja', 'activo' => false]);

    $this->actingAs($gerente)->post(route('gerente.productos.store', $team), productoJson(['categoria' => 'vieja']))
        ->assertSessionHasErrors('categoria');
    $this->actingAs($gerente)->post(route('gerente.productos.store', $team), productoJson(['categoria' => 'epp']))
        ->assertSessionHasNoErrors();

    $this->actingAs($gerente)->post(route('gerente.servicios.store', $team), [
        'codigo' => 'SRV-E-1', 'nombre' => 'Servicio E', 'unidad_medida' => 'ZZ', 'precio_venta' => 50,
        'aplica_igv' => true, 'categoria' => 'otro', 'activo' => true,
    ])->assertSessionHasNoErrors();
    expect(Service::where('codigo', 'SRV-E-1')->sole()->categoria)->toBe('otro');
});

// A3 unidad de medida

test('la unidad de medida de un servicio debe estar en el catalogo 03', function () {
    $gerente = gerenteFaseE();

    $this->actingAs($gerente)->post(route('gerente.servicios.store', ['current_team' => $gerente->currentTeam]), [
        'codigo' => 'SRV-E-2', 'nombre' => 'Servicio', 'unidad_medida' => 'XYZ', 'precio_venta' => 50,
        'aplica_igv' => true, 'activo' => true,
    ])->assertSessionHasErrors('unidad_medida');
});

test('greenter rechaza una unidad desconocida en vez de cambiarla', function () {
    $codigo = fn (Product|Service $item) => (fn () => $this->unidadCatalogo03($item))->call(app(GreenterService::class));

    expect($codigo(new Service(['unidad_medida' => 'ZZ'])))->toBe('ZZ')
        ->and($codigo(new Service(['unidad_medida' => 'HUR'])))->toBe('HUR')
        ->and(fn () => $codigo(new Product(['nombre' => 'Raro', 'unidad_medida' => 'UND'])))
        ->toThrow(RuntimeException::class, 'catálogo 03');
});

// A5 doble baja

test('una unidad no se da de baja dos veces', function () {
    $gerente = gerenteFaseE();
    $sede = Sede::factory()->create(['tipo' => 'almacen', 'activo' => true]);
    $producto = Product::factory()->create(['serializado' => true]);
    $unidad = InventoryUnit::create([
        'product_id' => $producto->id, 'sede_almacen_id' => $sede->id, 'numero_serie' => 'BF-EQ-000900',
        'estado' => 'disponible', 'fecha_ingreso' => today(),
    ]);
    $datos = [
        'product_id' => $producto->id, 'inventory_unit_id' => $unidad->id, 'sede_id' => $sede->id,
        'tipo_ajuste' => 'decremento', 'cantidad' => 1, 'motivo' => 'Se encontró dañado en el conteo',
    ];
    $ruta = route('almacen.ajustes.store', ['current_team' => $gerente->currentTeam]);

    $this->actingAs($gerente)->post($ruta, $datos)->assertSessionHasNoErrors();
    $this->actingAs($gerente)->post($ruta, $datos)->assertSessionHasErrors('inventory_unit_id');

    expect(InventoryMovement::where('inventory_unit_id', $unidad->id)->count())->toBe(1);
});

// A6 anular pago desde Cobranzas del Gerente

test('el gerente anula un pago desde cobranzas con motivo', function () {
    $gerente = gerenteFaseE();
    $sale = Sale::factory()->create(['estado' => 'confirmada']);
    $cuota = Installment::factory()->create(['sale_id' => $sale->id, 'monto' => 300, 'estado' => 'parcial']);
    $pago = SalePayment::factory()->create([
        'sale_id' => $sale->id, 'installment_id' => $cuota->id, 'monto' => 100, 'fecha' => today()->subDays(3),
    ]);
    $ruta = route('gerente.cobranzas.pagos.anular', ['current_team' => $gerente->currentTeam, 'payment' => $pago->id]);

    $this->actingAs($gerente)->delete($ruta, [])->assertSessionHasErrors('motivo');
    $this->actingAs($gerente)->delete($ruta, ['motivo' => 'Se registró dos veces'])->assertSessionHasNoErrors();

    expect(SalePayment::withTrashed()->find($pago->id)->anulado_motivo)->toBe('Se registró dos veces')
        ->and($cuota->refresh()->estado)->not->toBe('parcial');
});

// A1 costo y valorización

test('la recepcion calcula el costo promedio y el reporte valoriza al costo y avisa si falta', function () {
    $gerente = gerenteFaseE();
    $sede = Sede::factory()->create(['tipo' => 'almacen', 'activo' => true]);
    $producto = Product::factory()->create(['serializado' => false, 'precio_venta' => 99, 'activo' => true]);
    $sinCosto = Product::factory()->create(['serializado' => false, 'precio_venta' => 50, 'activo' => true]);
    $team = ['current_team' => $gerente->currentTeam];

    $recibir = fn (Product $p, int $cantidad, ?float $costo) => $this->actingAs($gerente)->post(route('almacen.recepciones.store', $team), [
        'proveedor' => 'Proveedor SAC', 'fecha' => today()->toDateString(), 'sede_almacen_id' => $sede->id,
        'items' => [['product_id' => $p->id, 'cantidad' => $cantidad, 'cantidad_conforme' => $cantidad, 'costo_unitario' => $costo]],
    ])->assertSessionHasNoErrors();

    $recibir($producto, 10, 10.0);
    $recibir($producto, 10, 20.0);
    $recibir($sinCosto, 4, null);

    expect((float) $producto->refresh()->costo_promedio)->toBe(15.0)
        ->and($sinCosto->refresh()->costo_promedio)->toBeNull();

    $this->actingAs($gerente)->get(route('gerente.reportes.index', [...$team, 'tipo' => 'inventario']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('reporteInventario.valorizacionTotal', 300)
            ->where('reporteInventario.productosSinCosto', 1)
            ->where('reporteInventario.totalUnidades', 24)
            ->has('reporteInventario.paginacion'));
});

// Stickers

test('los stickers salen en hoja de 20 y se puede empezar en una posicion o reimprimir uno', function () {
    $gerente = gerenteFaseE();
    $sede = Sede::factory()->create(['tipo' => 'almacen', 'activo' => true]);
    $producto = Product::factory()->create(['serializado' => true]);
    InventoryUnit::create([
        'product_id' => $producto->id, 'sede_almacen_id' => $sede->id, 'numero_serie' => 'BF-EQ-000777',
        'estado' => 'disponible', 'fecha_ingreso' => today(),
    ]);
    $team = ['current_team' => $gerente->currentTeam];

    $this->actingAs($gerente)->get(route('almacen.stickers.unidad', [...$team, 'serie' => 'bf-eq-000777', 'inicio' => 6]))
        ->assertOk()->assertHeader('Content-Type', 'application/pdf');
    $this->actingAs($gerente)->get(route('almacen.stickers.unidad', [...$team, 'serie' => 'BF-EQ-000777', 'inicio' => 21]))
        ->assertSessionHasErrors('inicio');
    $this->actingAs($gerente)->get(route('almacen.stickers.unidad', [...$team, 'serie' => 'BF-EQ-NO-EXISTE']))
        ->assertNotFound();
});

// A7 paginación en la base de datos

test('el stock se pagina en la base de datos', function () {
    $gerente = gerenteFaseE();
    Sede::factory()->create(['tipo' => 'almacen', 'activo' => true]);
    Product::factory()->count(20)->create(['activo' => true]);

    $this->actingAs($gerente)->get(route('almacen.stock.index', ['current_team' => $gerente->currentTeam]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('items.data', 15)
            ->where('items.total', 20)
            ->has('items.data.0.stock_por_sede'));
});
