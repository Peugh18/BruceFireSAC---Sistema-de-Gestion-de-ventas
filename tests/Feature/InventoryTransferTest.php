<?php

use App\Actions\Almacen\TransferInventory;
use App\Enums\TeamRole;
use App\Models\InventoryMovement;
use App\Models\InventoryUnit;
use App\Models\Product;
use App\Models\Sede;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->team = Team::factory()->create();
    $this->source = Sede::factory()->create(['tipo' => 'almacen', 'activo' => true]);
    $this->destination = Sede::factory()->create(['tipo' => 'mixta', 'activo' => true]);
    $this->user = User::factory()->create(['current_team_id' => $this->team->id, 'sede_id' => $this->source->id]);
    $this->team->members()->attach($this->user, ['role' => TeamRole::Admin->value]);
    $this->user->assignRole('Almacen');
});

test('traslado serializado registra salida entrada y mueve la unidad atomicamente', function () {
    $unit = InventoryUnit::factory()->create(['sede_almacen_id' => $this->source->id, 'estado' => 'disponible']);

    app(TransferInventory::class)->handle($this->source->id, ['destination_sede_id' => $this->destination->id, 'serials' => [$unit->numero_serie]], $this->user);

    expect($unit->refresh()->sede_almacen_id)->toBe($this->destination->id)
        ->and(InventoryMovement::where('inventory_unit_id', $unit->id)->where('tipo', 'traslado')->pluck('cantidad')->sort()->values()->all())->toBe([-1, 1]);
});

test('traslado de producto sin serie conserva el saldo total', function () {
    $product = Product::factory()->create(['serializado' => false]);
    InventoryMovement::create(['product_id' => $product->id, 'sede_id' => $this->source->id, 'tipo' => 'ingreso', 'cantidad' => 10, 'user_id' => $this->user->id]);

    app(TransferInventory::class)->handle($this->source->id, ['destination_sede_id' => $this->destination->id, 'product_id' => $product->id, 'quantity' => 4], $this->user);

    expect((int) InventoryMovement::where('product_id', $product->id)->sum('cantidad'))->toBe(10)
        ->and((int) InventoryMovement::where('product_id', $product->id)->where('sede_id', $this->destination->id)->sum('cantidad'))->toBe(4);
});
