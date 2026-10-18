<?php

use App\Models\AuditLog;
use App\Models\Product;
use App\Models\Service;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->gerente = User::factory()->create();
    $this->gerente->assignRole('Gerente');
    $this->actingAs($this->gerente);
});

test('el alta, la edición y el cambio de estado de un producto quedan en auditoría con antes y después', function () {
    $producto = Product::factory()->create(['nombre' => 'Válvula', 'stock_minimo' => 2, 'activo' => true]);
    $producto->update(['stock_minimo' => 5]);
    $producto->update(['activo' => false]);

    expect(AuditLog::query()->where('action', 'producto.creado')->exists())->toBeTrue();

    $edicion = AuditLog::query()->where('action', 'producto.actualizado')->sole();
    expect($edicion->old_values)->toBe(['stock_minimo' => 2])
        ->and($edicion->new_values)->toBe(['stock_minimo' => 5])
        ->and(AuditLog::query()->where('action', 'producto.desactivado')->exists())->toBeTrue();
});

test('los servicios también quedan en auditoría', function () {
    $servicio = Service::factory()->create(['precio_venta' => 40]);
    $servicio->update(['precio_venta' => 45]);

    expect(AuditLog::query()->where('action', 'servicio.creado')->exists())->toBeTrue()
        ->and(AuditLog::query()->where('action', 'servicio.actualizado')->exists())->toBeTrue();
});

test('el visor muestra los cambios en palabras, sin objetos ni nombres de columnas', function () {
    $producto = Product::factory()->create(['activo' => true, 'stock_minimo' => 1]);
    $producto->update(['stock_minimo' => 3, 'activo' => false]);

    $this->get(route('gerente.auditoria.index', ['current_team' => $this->gerente->currentTeam, 'accion' => 'producto.desactivado']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('registros.data.0.entidad', 'Producto '.$producto->codigo)
            ->where('registros.data.0.cambios', [
                ['campo' => 'Activo', 'antes' => 'Sí', 'despues' => 'No'],
                ['campo' => 'Stock mínimo', 'antes' => '1', 'despues' => '3'],
            ]));
});
