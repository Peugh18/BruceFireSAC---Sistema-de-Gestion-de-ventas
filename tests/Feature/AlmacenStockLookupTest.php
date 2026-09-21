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

if (! function_exists('almacenUserForLookupTest')) {
    function almacenUserForLookupTest(): User
    {
        $user = User::factory()->create();
        $user->assignRole('Almacen');

        return $user;
    }
}

test('almacen user can view consulta index page', function () {
    $user = almacenUserForLookupTest();
    $sede = Sede::factory()->create(['tipo' => 'almacen', 'activo' => true]);

    $this->actingAs($user)
        ->get(route('almacen.consulta.index', ['current_team' => $user->currentTeam]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('almacen/consulta/index')
            ->has('sedes')
            ->where('unitResult', null)
            ->where('productResult', null)
        );
});

test('consulta por serie encuentra unidad fisica e incluye unidades no disponibles (vendido/baja)', function () {
    $user = almacenUserForLookupTest();
    $sede = Sede::factory()->create(['tipo' => 'almacen', 'activo' => true]);

    $prod = Product::factory()->create([
        'codigo' => 'EXT-CO2-10',
        'nombre' => 'Extintor CO2 10lb',
        'serializado' => true,
    ]);

    // Unidad dada de baja o vendida (regla dura: Almacén consulta TODAS las unidades, no filtra estaDisponible)
    $unit = InventoryUnit::create([
        'product_id' => $prod->id,
        'sede_almacen_id' => $sede->id,
        'numero_serie' => 'BF-EQ-000456',
        'marca' => 'Kidde',
        'anio_fabricacion' => 2025,
        'estado' => 'baja',
        'fecha_ingreso' => today()->subMonths(3),
    ]);

    // Crear movimientos de Kardex para verificar historial
    InventoryMovement::create([
        'inventory_unit_id' => $unit->id,
        'product_id' => $prod->id,
        'sede_id' => $sede->id,
        'tipo' => 'ingreso',
        'cantidad' => 1,
        'user_id' => $user->id,
        'observacion' => 'Ingreso por recepción inicial',
    ]);

    InventoryMovement::create([
        'inventory_unit_id' => $unit->id,
        'product_id' => $prod->id,
        'sede_id' => $sede->id,
        'tipo' => 'ajuste',
        'cantidad' => -1,
        'user_id' => $user->id,
        'observacion' => 'Baja por fuga en prueba hidrostática',
    ]);

    $response = $this->actingAs($user)
        ->get(route('almacen.consulta.index', [
            'current_team' => $user->currentTeam,
            'search' => 'BF-EQ-000456',
        ]))
        ->assertOk();

    $response->assertInertia(fn (Assert $page) => $page
        ->component('almacen/consulta/index')
        ->where('unitResult.numero_serie', 'BF-EQ-000456')
        ->where('unitResult.estado', 'baja')
        ->where('unitResult.marca', 'Kidde')
        ->has('unitResult.movimientos', 2)
    );
});

test('consulta por codigo de producto a granel muestra stock y movimientos', function () {
    $user = almacenUserForLookupTest();
    $sede = Sede::factory()->create(['tipo' => 'almacen', 'activo' => true]);

    $manguera = Product::factory()->create([
        'codigo' => 'MANG-25M',
        'nombre' => 'Manguera sintética 25m',
        'serializado' => false,
    ]);

    InventoryMovement::create([
        'product_id' => $manguera->id,
        'sede_id' => $sede->id,
        'tipo' => 'ingreso',
        'cantidad' => 20,
        'user_id' => $user->id,
        'observacion' => 'Lote de mangueras',
    ]);

    $this->actingAs($user)
        ->get(route('almacen.consulta.index', [
            'current_team' => $user->currentTeam,
            'search' => 'MANG-25M',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('almacen/consulta/index')
            ->where('productResult.codigo', 'MANG-25M')
            ->where('productResult.stock_total', 20)
            ->has('productResult.movimientos', 1)
        );
});

test('endpoint de busqueda json para autocompletar', function () {
    $user = almacenUserForLookupTest();
    $sede = Sede::factory()->create(['tipo' => 'almacen', 'activo' => true]);
    $prod = Product::factory()->create();

    InventoryUnit::create([
        'product_id' => $prod->id,
        'sede_almacen_id' => $sede->id,
        'numero_serie' => 'BF-EQ-000789',
        'estado' => 'disponible',
        'fecha_ingreso' => today(),
    ]);

    $response = $this->actingAs($user)
        ->getJson(route('almacen.consulta.buscar', [
            'current_team' => $user->currentTeam,
            'q' => '000789',
        ]));

    $response->assertOk();
    $response->assertJsonFragment([
        'numero_serie' => 'BF-EQ-000789',
        'estado' => 'disponible',
    ]);
});

test('usuario sin rol Almacen no puede acceder a consulta', function () {
    $user = User::factory()->create();
    $user->assignRole('Vendedor');

    $this->actingAs($user)
        ->get(route('almacen.consulta.index', ['current_team' => $user->currentTeam]))
        ->assertForbidden();
});
