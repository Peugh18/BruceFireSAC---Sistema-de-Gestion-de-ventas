<?php

use App\Models\AuditLog;
use App\Models\Product;
use App\Models\Sede;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $sede = Sede::factory()->mixta()->create();
    $this->almacenero = User::factory()->create(['sede_id' => $sede->id]);
    $this->almacenero->assignRole('Almacen');
    $this->ruta = fn (Product $producto) => route('almacen.productos.codigo-barras', ['current_team' => $this->almacenero->currentTeam, 'producto' => $producto]);
});

test('almacén liga el código de barras del fabricante a un producto al recibirlo', function () {
    $guante = Product::factory()->create(['codigo_barras' => null]);

    $this->actingAs($this->almacenero)
        ->post(($this->ruta)($guante), ['codigo_barras' => '7751234567890'])
        ->assertSessionHasNoErrors();

    expect($guante->fresh()->codigo_barras)->toBe('7751234567890')
        ->and(AuditLog::query()->where('action', 'producto.actualizado')->exists())->toBeTrue();
});

test('no se repite un código de barras ni se pisa el que ya tiene el producto', function () {
    Product::factory()->create(['codigo_barras' => '7750000000011']);
    $otro = Product::factory()->create(['codigo_barras' => null]);
    $conCodigo = Product::factory()->create(['codigo_barras' => '7759999999999']);

    $this->actingAs($this->almacenero)
        ->post(($this->ruta)($otro), ['codigo_barras' => '7750000000011'])
        ->assertSessionHasErrors('codigo_barras');

    $this->post(($this->ruta)($conCodigo), ['codigo_barras' => '7751111111111'])
        ->assertSessionHasErrors('codigo_barras');

    expect($conCodigo->fresh()->codigo_barras)->toBe('7759999999999');
});
