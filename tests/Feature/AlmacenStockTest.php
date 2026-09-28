<?php

use App\Models\InventoryMovement;
use App\Models\InventoryUnit;
use App\Models\Product;
use App\Models\Sede;
use App\Models\Service;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

if (! function_exists('almacenUserForStockTest')) {
    function almacenUserForStockTest(): User
    {
        $user = User::factory()->create();
        $user->assignRole('Almacen');

        return $user;
    }
}

test('almacen stock index lista solo productos con su stock por sede', function () {
    $user = almacenUserForStockTest();

    $sedeA = Sede::factory()->create(['nombre' => 'Sede Lima', 'tipo' => 'almacen', 'activo' => true]);
    $sedeB = Sede::factory()->create(['nombre' => 'Sede Callao', 'tipo' => 'mixta', 'activo' => true]);

    $prod = Product::factory()->create([
        'codigo' => 'PROD-001',
        'nombre' => 'Extintor PQS 6kg ABC',
        'precio_venta' => 120.00,
        'activo' => true,
    ]);

    $serv = Service::factory()->create([
        'codigo' => 'SERV-001',
        'nombre' => 'Recarga PQS 6kg',
        'precio_venta' => 45.00,
        'activo' => true,
    ]);

    // Stock para el producto: 3 en sedeA, 1 en sedeB
    InventoryUnit::factory()->count(3)->create([
        'product_id' => $prod->id,
        'sede_almacen_id' => $sedeA->id,
        'estado' => 'disponible',
    ]);
    InventoryUnit::factory()->create([
        'product_id' => $prod->id,
        'sede_almacen_id' => $sedeB->id,
        'estado' => 'disponible',
    ]);

    $response = $this->actingAs($user)
        ->get(route('almacen.stock.index', ['current_team' => $user->currentTeam]))
        ->assertOk();

    $response->assertInertia(fn (Assert $page) => $page
        ->component('almacen/stock/index')
        ->has('items.data', 1)
        ->has('sedes', 2)
        ->has('kardex.data')
    );

    $items = collect($response->viewData('page')['props']['items']['data']);
    $prodRow = $items->firstWhere('codigo', 'PROD-001');
    expect($prodRow['stock_disponible_total'])->toBe(4)
        ->and($prodRow['stock_por_sede'][$sedeA->id])->toBe(3)
        ->and($prodRow['stock_por_sede'][$sedeB->id])->toBe(1)
        ->and($items->pluck('codigo'))->not->toContain($serv->codigo);
});

test('almacen stock index permite buscar productos y nunca devuelve servicios', function () {
    $user = almacenUserForStockTest();

    Product::factory()->create(['codigo' => 'EXT-ABC', 'nombre' => 'Extintor PQS']);
    Service::factory()->create(['codigo' => 'REC-PQS', 'nombre' => 'Recarga Extintor']);

    // Filtrar solo productos
    $resProd = $this->actingAs($user)
        ->get(route('almacen.stock.index', [
            'current_team' => $user->currentTeam,
            'tipo' => 'producto',
        ]))
        ->assertOk();

    $itemsProd = collect($resProd->viewData('page')['props']['items']['data']);
    expect($itemsProd->pluck('tipo')->unique()->all())->toBe(['producto']);

    // El parámetro antiguo de servicios ya no los expone.
    $resServ = $this->actingAs($user)
        ->get(route('almacen.stock.index', [
            'current_team' => $user->currentTeam,
            'tipo' => 'servicio',
        ]))
        ->assertOk();

    $itemsServ = collect($resServ->viewData('page')['props']['items']['data']);
    expect($itemsServ)->toBeEmpty();

    // Búsqueda por texto
    $resSearch = $this->actingAs($user)
        ->get(route('almacen.stock.index', [
            'current_team' => $user->currentTeam,
            'search' => 'REC-PQS',
        ]))
        ->assertOk();

    $itemsSearch = collect($resSearch->viewData('page')['props']['items']['data']);
    expect($itemsSearch)->toBeEmpty();
});

test('almacen kardex permite filtrar movimientos por producto, sede, fecha y tipo', function () {
    $user = almacenUserForStockTest();

    $sede1 = Sede::factory()->create(['tipo' => 'almacen']);
    $sede2 = Sede::factory()->create(['tipo' => 'almacen']);

    $prod1 = Product::factory()->create();
    $prod2 = Product::factory()->create();

    // Movimiento 1: Prod1, Sede1, ingreso, fecha hoy
    $mov1 = new InventoryMovement([
        'product_id' => $prod1->id,
        'sede_id' => $sede1->id,
        'tipo' => 'ingreso',
        'cantidad' => 10,
        'user_id' => $user->id,
    ]);
    $mov1->created_at = today();
    $mov1->save();

    // Movimiento 2: Prod1, Sede2, salida_venta, fecha hoy
    $mov2 = new InventoryMovement([
        'product_id' => $prod1->id,
        'sede_id' => $sede2->id,
        'tipo' => 'salida_venta',
        'cantidad' => 2,
        'user_id' => $user->id,
    ]);
    $mov2->created_at = today();
    $mov2->save();

    // Movimiento 3: Prod2, Sede1, ajuste, fecha hace 5 días
    $mov3 = new InventoryMovement([
        'product_id' => $prod2->id,
        'sede_id' => $sede1->id,
        'tipo' => 'ajuste',
        'cantidad' => 1,
        'user_id' => $user->id,
    ]);
    $mov3->created_at = today()->subDays(5);
    $mov3->save();

    // Filtrar por product_id = prod1
    $resProduct = $this->actingAs($user)
        ->get(route('almacen.stock.index', [
            'current_team' => $user->currentTeam,
            'kardex_product_id' => $prod1->id,
        ]))
        ->assertOk();

    $kardexData1 = collect($resProduct->viewData('page')['props']['kardex']['data']);
    expect($kardexData1)->toHaveCount(2)
        ->and($kardexData1->pluck('id')->all())->toContain($mov1->id, $mov2->id);

    // Filtrar por tipo = salida_venta
    $resTipo = $this->actingAs($user)
        ->get(route('almacen.stock.index', [
            'current_team' => $user->currentTeam,
            'kardex_tipo' => 'salida_venta',
        ]))
        ->assertOk();

    $kardexDataTipo = collect($resTipo->viewData('page')['props']['kardex']['data']);
    expect($kardexDataTipo)->toHaveCount(1)
        ->and($kardexDataTipo->first()['id'])->toBe($mov2->id);

    // Filtrar por sede_id = sede2
    $resSede = $this->actingAs($user)
        ->get(route('almacen.stock.index', [
            'current_team' => $user->currentTeam,
            'kardex_sede_id' => $sede2->id,
        ]))
        ->assertOk();

    $kardexDataSede = collect($resSede->viewData('page')['props']['kardex']['data']);
    expect($kardexDataSede)->toHaveCount(1)
        ->and($kardexDataSede->first()['id'])->toBe($mov2->id);
});

test('almacen stock index pagina el catalogo cuando hay mas de 15 items', function () {
    $user = almacenUserForStockTest();

    Product::factory()->count(20)->create(['activo' => true]);

    $response = $this->actingAs($user)
        ->get(route('almacen.stock.index', ['current_team' => $user->currentTeam]))
        ->assertOk();

    $response->assertInertia(fn (Assert $page) => $page
        ->component('almacen/stock/index')
        ->has('items.data', 15)
        ->where('items.total', 20)
        ->where('items.last_page', 2)
    );
});

test('usuario no autorizado no puede acceder al modulo de stock', function () {
    $vendedor = User::factory()->create();
    $vendedor->assignRole('Vendedor');

    $this->actingAs($vendedor)
        ->get(route('almacen.stock.index', ['current_team' => $vendedor->currentTeam]))
        ->assertForbidden();
});
