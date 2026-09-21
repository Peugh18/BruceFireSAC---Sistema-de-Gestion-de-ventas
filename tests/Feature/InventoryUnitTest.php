<?php

use App\Models\CatalogItem;
use App\Models\InventoryUnit;
use App\Models\Sede;
use Illuminate\Database\QueryException;

test('numero_serie unico falla con excepcion si se duplica', function () {
    InventoryUnit::factory()->create([
        'numero_serie' => 'SN-DUPLICATE-001',
    ]);

    $this->expectException(QueryException::class);

    InventoryUnit::factory()->create([
        'numero_serie' => 'SN-DUPLICATE-001',
    ]);
});

test('estaDisponible retorna true unicamente cuando el estado es disponible', function () {
    $disponible = InventoryUnit::factory()->create(['estado' => 'disponible']);
    $reservado = InventoryUnit::factory()->create(['estado' => 'reservado']);
    $vendido = InventoryUnit::factory()->vendido()->create();
    $baja = InventoryUnit::factory()->create(['estado' => 'baja']);

    expect($disponible->estaDisponible())->toBeTrue()
        ->and($reservado->estaDisponible())->toBeFalse()
        ->and($vendido->estaDisponible())->toBeFalse()
        ->and($baja->estaDisponible())->toBeFalse();
});

test('relaciones catalogItem y sedeAlmacen cargan correctamente', function () {
    $catalogItem = CatalogItem::factory()->producto()->create([
        'nombre' => 'Extintor Acetato 6L',
    ]);
    $sede = Sede::factory()->almacen()->create([
        'nombre' => 'Almacen Central Bruce',
    ]);

    $unit = InventoryUnit::factory()->create([
        'catalog_item_id' => $catalogItem->id,
        'sede_almacen_id' => $sede->id,
    ]);

    expect($unit->catalogItem)->toBeInstanceOf(CatalogItem::class)
        ->and($unit->catalogItem->id)->toBe($catalogItem->id)
        ->and($unit->catalogItem->nombre)->toBe('Extintor Acetato 6L')
        ->and($unit->sedeAlmacen)->toBeInstanceOf(Sede::class)
        ->and($unit->sedeAlmacen->id)->toBe($sede->id)
        ->and($unit->sedeAlmacen->nombre)->toBe('Almacen Central Bruce');
});
