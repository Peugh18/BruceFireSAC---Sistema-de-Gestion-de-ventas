<?php

use App\Models\InventoryUnit;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function createGerenteUserForProductTest(): User
{
    $user = User::factory()->create();
    $user->assignRole('Gerente');

    return $user;
}

test('gerente puede ver listado de productos con kpis y filtros', function () {
    $user = createGerenteUserForProductTest();
    Product::factory()->create([
        'codigo' => 'TEST-01',
        'nombre' => 'Extintor Test',
        'precio_venta' => 150.00,
        'stock_minimo' => 10,
        'activo' => true,
    ]);

    $this->actingAs($user)
        ->get(route('gerente.productos.index', ['current_team' => $user->currentTeam]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('gerente/productos/index')
            ->has('productos.data', 1)
            ->has('kpis.totalProductos')
            ->has('kpis.totalActivos')
            ->has('kpis.totalBajoMinimo')
        );
});

test('vendedor no puede acceder al crud de productos de gerente', function () {
    $vendedor = User::factory()->create();
    $vendedor->assignRole('Vendedor');

    $this->actingAs($vendedor)
        ->get(route('gerente.productos.index', ['current_team' => $vendedor->currentTeam]))
        ->assertForbidden();
});

test('gerente puede crear un nuevo producto con stock minimo', function () {
    $user = createGerenteUserForProductTest();

    $data = [
        'codigo' => 'PROD-NEW-01',
        'nombre' => 'Extintor de Prueba 6kg',
        'descripcion' => 'Descripción técnica de prueba',
        'unidad_medida' => 'NIU',
        'precio_venta' => 85.50,
        'aplica_igv' => true,
        'serializado' => true,
        'stock_minimo' => 5,
        'activo' => true,
    ];

    $this->actingAs($user)
        ->post(route('gerente.productos.store', ['current_team' => $user->currentTeam]), $data)
        ->assertRedirect(route('gerente.productos.index', ['current_team' => $user->currentTeam]));

    $this->assertDatabaseHas('products', [
        'codigo' => 'PROD-NEW-01',
        'nombre' => 'Extintor de Prueba 6kg',
        'precio_venta' => 85.50,
        'stock_minimo' => 5,
        'serializado' => 1,
    ]);
});

test('al crear un producto el catalogo muestra el aviso de producto creado', function () {
    $user = createGerenteUserForProductTest();

    $this->actingAs($user)
        ->followingRedirects()
        ->post(route('gerente.productos.store', ['current_team' => $user->currentTeam]), [
            'codigo' => 'PROD-AVISO-01',
            'nombre' => 'Extintor CO2 5kg',
            'unidad_medida' => 'NIU',
            'precio_venta' => 250,
        ])
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('gerente/productos/index')
            ->where('flash.success', 'Producto creado exitosamente.')
        );
});

test('gerente puede editar un producto existente y modificar stock minimo', function () {
    $user = createGerenteUserForProductTest();
    $product = Product::factory()->create([
        'codigo' => 'PROD-EDIT-01',
        'precio_venta' => 50.00,
        'stock_minimo' => 3,
    ]);

    $data = [
        'codigo' => 'PROD-EDIT-01',
        'nombre' => 'Nombre Modificado',
        'unidad_medida' => 'NIU',
        'precio_venta' => 99.90,
        'aplica_igv' => true,
        'serializado' => false,
        'stock_minimo' => 15,
        'activo' => true,
    ];

    $this->actingAs($user)
        ->put(route('gerente.productos.update', ['current_team' => $user->currentTeam, 'producto' => $product]), $data)
        ->assertRedirect(route('gerente.productos.index', ['current_team' => $user->currentTeam]));

    $this->assertDatabaseHas('products', [
        'id' => $product->id,
        'nombre' => 'Nombre Modificado',
        'precio_venta' => 99.90,
        'stock_minimo' => 15,
    ]);
});

test('gerente puede alternar el estado activo del producto', function () {
    $user = createGerenteUserForProductTest();
    $product = Product::factory()->create(['activo' => true]);

    $this->actingAs($user)
        ->patch(route('gerente.productos.toggle-status', ['current_team' => $user->currentTeam, 'producto' => $product]))
        ->assertRedirect();

    $this->assertDatabaseHas('products', [
        'id' => $product->id,
        'activo' => 0,
    ]);
});

test('al eliminar un producto con historial se desactiva y conserva sus registros', function () {
    $user = createGerenteUserForProductTest();
    $product = Product::factory()->create();

    // Agregar unidad asociada
    InventoryUnit::factory()->create([
        'product_id' => $product->id,
    ]);

    $this->actingAs($user)
        ->delete(route('gerente.productos.destroy', ['current_team' => $user->currentTeam, 'producto' => $product]))
        ->assertRedirect()
        ->assertSessionHas('success');

    $this->assertDatabaseHas('products', ['id' => $product->id, 'activo' => false]);
    $this->assertDatabaseHas('inventory_units', ['product_id' => $product->id]);
});

test('al eliminar un producto sin historial tambien se desactiva', function () {
    $user = createGerenteUserForProductTest();
    $product = Product::factory()->create();

    $this->actingAs($user)
        ->delete(route('gerente.productos.destroy', ['current_team' => $user->currentTeam, 'producto' => $product]))
        ->assertRedirect(route('gerente.productos.index', ['current_team' => $user->currentTeam]))
        ->assertSessionHas('success');

    $this->assertDatabaseHas('products', ['id' => $product->id, 'activo' => false]);
});
