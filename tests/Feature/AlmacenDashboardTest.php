<?php

use App\Models\InventoryMovement;
use App\Models\InventoryUnit;
use App\Models\Product;
use App\Models\Sede;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

if (! function_exists('almacenUserForDashboardTest')) {
    function almacenUserForDashboardTest(): User
    {
        $user = User::factory()->create();
        $user->assignRole('Almacen');

        return $user;
    }
}

test('dashboard de almacen expone los KPIs de unidades disponibles por sede', function () {
    $user = almacenUserForDashboardTest();

    $sedeA = Sede::factory()->create(['nombre' => 'Almacén Central', 'tipo' => 'almacen', 'activo' => true]);
    $sedeB = Sede::factory()->create(['nombre' => 'Almacén Norte', 'tipo' => 'almacen', 'activo' => true]);

    $product = Product::factory()->create(['serializado' => true]);

    // Sede A: 2 disponibles, 1 vendido
    InventoryUnit::factory()->create(['product_id' => $product->id, 'sede_almacen_id' => $sedeA->id, 'estado' => 'disponible']);
    InventoryUnit::factory()->create(['product_id' => $product->id, 'sede_almacen_id' => $sedeA->id, 'estado' => 'disponible']);
    InventoryUnit::factory()->create(['product_id' => $product->id, 'sede_almacen_id' => $sedeA->id, 'estado' => 'vendido']);

    // Sede B: 1 disponible
    InventoryUnit::factory()->create(['product_id' => $product->id, 'sede_almacen_id' => $sedeB->id, 'estado' => 'disponible']);

    $response = $this->actingAs($user)
        ->get(route('almacen.dashboard', ['current_team' => $user->currentTeam]))
        ->assertOk();

    $response->assertInertia(fn (Assert $page) => $page
        ->component('almacen/dashboard')
        ->where('stock.total_disponible', 3)
        ->has('stock.por_sede', fn (Assert $sedes) => $sedes
            ->each(fn (Assert $sede) => $sede
                ->hasAll(['sede_id', 'nombre', 'tipo', 'ciudad', 'unidades_disponibles'])
            )
        )
    );

    $porSede = collect($response->viewData('page')['props']['stock']['por_sede']);
    expect($porSede->firstWhere('sede_id', $sedeA->id)['unidades_disponibles'])->toBe(2)
        ->and($porSede->firstWhere('sede_id', $sedeB->id)['unidades_disponibles'])->toBe(1);
});

test('dashboard de almacen cuenta correctamente las recepciones de hoy', function () {
    $user = almacenUserForDashboardTest();
    $sede = Sede::factory()->create(['tipo' => 'almacen']);
    $product = Product::factory()->create();

    // 2 ingresos hoy
    $mov1 = new InventoryMovement([
        'product_id' => $product->id,
        'sede_id' => $sede->id,
        'tipo' => 'ingreso',
        'cantidad' => 10,
        'user_id' => $user->id,
    ]);
    $mov1->created_at = today()->setHour(9);
    $mov1->save();

    $mov2 = new InventoryMovement([
        'product_id' => $product->id,
        'sede_id' => $sede->id,
        'tipo' => 'ingreso',
        'cantidad' => 5,
        'user_id' => $user->id,
    ]);
    $mov2->created_at = today()->setHour(11);
    $mov2->save();

    // 1 salida hoy (no es recepción)
    $mov3 = new InventoryMovement([
        'product_id' => $product->id,
        'sede_id' => $sede->id,
        'tipo' => 'salida_venta',
        'cantidad' => 1,
        'user_id' => $user->id,
    ]);
    $mov3->created_at = today()->setHour(12);
    $mov3->save();

    // 1 ingreso de ayer (no es de hoy)
    $mov4 = new InventoryMovement([
        'product_id' => $product->id,
        'sede_id' => $sede->id,
        'tipo' => 'ingreso',
        'cantidad' => 20,
        'user_id' => $user->id,
    ]);
    $mov4->created_at = today()->subDay();
    $mov4->save();

    $response = $this->actingAs($user)
        ->get(route('almacen.dashboard', ['current_team' => $user->currentTeam]))
        ->assertOk();

    expect($response->viewData('page')['props']['recepciones_hoy'])->toBe(2);
});

test('dashboard de almacen expone los ultimos 10 movimientos del Kardex con sus relaciones', function () {
    $user = almacenUserForDashboardTest();
    $sede = Sede::factory()->create(['nombre' => 'Sede Principal', 'tipo' => 'almacen']);
    $product = Product::factory()->create(['nombre' => 'Extintor PQS 6kg', 'serializado' => true]);
    $unit = InventoryUnit::factory()->create(['product_id' => $product->id, 'sede_almacen_id' => $sede->id, 'numero_serie' => 'BF-001']);

    for ($i = 1; $i <= 15; $i++) {
        InventoryMovement::create([
            'inventory_unit_id' => $unit->id,
            'product_id' => $product->id,
            'sede_id' => $sede->id,
            'tipo' => 'ingreso',
            'cantidad' => 1,
            'user_id' => $user->id,
            'observacion' => "Movimiento {$i}",
        ]);
    }

    $response = $this->actingAs($user)
        ->get(route('almacen.dashboard', ['current_team' => $user->currentTeam]))
        ->assertOk();

    $movimientos = $response->viewData('page')['props']['movimientos_recientes'];

    expect(count($movimientos))->toBe(10)
        ->and($movimientos[0]['observacion'])->toBe('Movimiento 15')
        ->and($movimientos[0]['producto']['nombre'])->toBe('Extintor PQS 6kg')
        ->and($movimientos[0]['unidad_serie'])->toBe('BF-001')
        ->and($movimientos[0]['sede'])->toBe('Sede Principal')
        ->and($movimientos[0]['usuario'])->toBe($user->name);
});

test('dashboard de almacen lista los productos cuyo stock disponible esta bajo el minimo', function () {
    $user = almacenUserForDashboardTest();
    $sede = Sede::factory()->create(['tipo' => 'almacen']);

    // Producto A: stock_minimo = 5, disponible = 2 -> DEBE aparecer (faltan 3)
    $prodBajo = Product::factory()->create([
        'nombre' => 'Extintor PQS 4kg',
        'stock_minimo' => 5,
        'activo' => true,
        'serializado' => true,
    ]);
    InventoryUnit::factory()->create(['product_id' => $prodBajo->id, 'sede_almacen_id' => $sede->id, 'estado' => 'disponible']);
    InventoryUnit::factory()->create(['product_id' => $prodBajo->id, 'sede_almacen_id' => $sede->id, 'estado' => 'disponible']);
    InventoryUnit::factory()->create(['product_id' => $prodBajo->id, 'sede_almacen_id' => $sede->id, 'estado' => 'vendido']);

    // Producto B: stock_minimo = 2, disponible = 3 -> NO debe aparecer
    $prodOk = Product::factory()->create([
        'nombre' => 'Extintor CO2 5lb',
        'stock_minimo' => 2,
        'activo' => true,
        'serializado' => true,
    ]);
    InventoryUnit::factory()->count(3)->create(['product_id' => $prodOk->id, 'sede_almacen_id' => $sede->id, 'estado' => 'disponible']);

    // Producto C: stock_minimo = null -> NO debe aparecer
    $prodSinMinimo = Product::factory()->create([
        'nombre' => 'Gabinete metálico',
        'stock_minimo' => null,
        'activo' => true,
    ]);
    InventoryUnit::factory()->create(['product_id' => $prodSinMinimo->id, 'sede_almacen_id' => $sede->id, 'estado' => 'disponible']);

    $response = $this->actingAs($user)
        ->get(route('almacen.dashboard', ['current_team' => $user->currentTeam]))
        ->assertOk();

    $bajoMinimo = $response->viewData('page')['props']['productos_bajo_minimo'];
    $ids = collect($bajoMinimo)->pluck('id')->all();

    expect($ids)->toContain($prodBajo->id)
        ->and($ids)->not->toContain($prodOk->id)
        ->and($ids)->not->toContain($prodSinMinimo->id);

    $itemBajo = collect($bajoMinimo)->firstWhere('id', $prodBajo->id);
    expect($itemBajo['stock_disponible'])->toBe(2)
        ->and($itemBajo['stock_minimo'])->toBe(5)
        ->and($itemBajo['diferencia'])->toBe(3);
});

test('usuario con rol Almacen que entra a /dashboard es redirigido a almacen/dashboard si no tiene invitaciones pendientes', function () {
    $user = almacenUserForDashboardTest();

    $response = $this->actingAs($user)
        ->get(route('dashboard', ['current_team' => $user->currentTeam]));

    $response->assertRedirect(route('almacen.dashboard', ['current_team' => $user->currentTeam]));
});
