<?php

use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Sede;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('gerencia registra un repuesto como producto en la categoria repuesto', function () {
    $gerente = User::factory()->create();
    $gerente->assignRole('Gerente');

    $this->actingAs($gerente)
        ->post(route('gerente.productos.store', ['current_team' => $gerente->currentTeam]), [
            'codigo' => 'REP-CAT-001',
            'nombre' => 'Manómetro para extintor',
            'categoria' => 'repuesto',
            'unidad_medida' => 'NIU',
            'precio_venta' => 35.50,
            'aplica_igv' => true,
            'serializado' => false,
            'activo' => true,
        ])
        ->assertSessionHasNoErrors();

    $producto = Product::query()->latest('id')->firstOrFail();

    expect($producto->categoria)->toBe('repuesto')
        ->and($producto->serializado)->toBeFalse()
        ->and(ProductCategory::where('clave', 'repuesto')->exists())->toBeTrue();
});

test('un repuesto conserva sus movimientos al desactivarse', function () {
    $producto = Product::factory()->create([
        'categoria' => 'repuesto',
        'serializado' => false,
        'activo' => true,
    ]);
    $sede = Sede::factory()->create(['tipo' => 'almacen', 'activo' => true]);
    InventoryMovement::create([
        'product_id' => $producto->id,
        'sede_id' => $sede->id,
        'tipo' => 'ingreso',
        'cantidad' => 3,
    ]);

    $producto->update(['activo' => false]);

    expect($producto->refresh()->categoria)->toBe('repuesto')
        ->and($producto->activo)->toBeFalse()
        ->and((int) $producto->movements()->sum('cantidad'))->toBe(3);
});
