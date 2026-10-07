<?php

use App\Models\Product;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('el gerente registra un epp con su codigo de barras y no se repite entre productos', function () {
    $gerente = User::factory()->create();
    $gerente->assignRole('Gerente');
    $team = ['current_team' => $gerente->currentTeam];
    $guante = [
        'codigo' => 'EPP-GUA-NIT-M', 'codigo_barras' => '7751234567890', 'categoria' => 'epp',
        'nombre' => 'Guante de nitrilo talla M', 'unidad_medida' => 'PR', 'precio_venta' => 12.5,
        'aplica_igv' => true, 'serializado' => false, 'stock_minimo' => 20, 'activo' => true,
    ];

    $this->actingAs($gerente)->post(route('gerente.productos.store', $team), $guante)->assertSessionHasNoErrors();

    expect(Product::where('codigo_barras', '7751234567890')->sole()->categoria)->toBe('epp');

    $this->actingAs($gerente)->post(route('gerente.productos.store', $team), [...$guante, 'codigo' => 'EPP-GUA-NIT-L', 'nombre' => 'Guante talla L'])
        ->assertSessionHasErrors('codigo_barras');
});

test('al escanear el codigo de barras en la venta sale ese producto primero', function () {
    $vendedor = User::factory()->create();
    $vendedor->assignRole('Vendedor');
    Product::factory()->create(['nombre' => 'Casco blanco', 'codigo' => 'EPP-CAS-1', 'codigo_barras' => '7750000000011', 'serializado' => false, 'activo' => true]);
    Product::factory()->create(['nombre' => 'Casco 7750000000011 rojo', 'codigo' => 'EPP-CAS-2', 'serializado' => false, 'activo' => true]);

    $this->actingAs($vendedor)
        ->getJson(route('vendedor.catalogo.buscar', ['current_team' => $vendedor->currentTeam, 'search' => '7750000000011']))
        ->assertOk()
        ->assertJsonPath('items.0.codigo', 'EPP-CAS-1')
        ->assertJsonPath('items.0.codigo_barras', '7750000000011');
});
