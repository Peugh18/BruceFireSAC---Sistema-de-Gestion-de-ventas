<?php

use App\Models\Sede;

test('puede crear sede de tipo almacen sin almacen_id', function () {
    $sede = Sede::factory()->almacen()->create([
        'almacen_id' => null,
    ]);

    expect($sede)->toBeInstanceOf(Sede::class)
        ->and($sede->tipo)->toBe('almacen')
        ->and($sede->almacen_id)->toBeNull()
        ->and($sede->esAlmacen())->toBeTrue();

    $this->assertDatabaseHas('sedes', [
        'id' => $sede->id,
        'tipo' => 'almacen',
        'almacen_id' => null,
    ]);
});

test('puede crear sede de tipo mixta sin almacen_id', function () {
    $sede = Sede::factory()->mixta()->create([
        'almacen_id' => null,
    ]);

    expect($sede)->toBeInstanceOf(Sede::class)
        ->and($sede->tipo)->toBe('mixta')
        ->and($sede->almacen_id)->toBeNull()
        ->and($sede->esAlmacen())->toBeTrue();

    $this->assertDatabaseHas('sedes', [
        'id' => $sede->id,
        'tipo' => 'mixta',
        'almacen_id' => null,
    ]);
});

test('crear sede de tipo tienda sin almacen_id debe lanzar excepcion', function () {
    expect(function () {
        Sede::factory()->tienda()->create([
            'almacen_id' => null,
        ]);
    })->toThrow(InvalidArgumentException::class, 'Una sede de tipo "tienda" debe indicar de qu');
});

test('puede crear sede de tipo tienda con almacen_id valido', function () {
    $almacen = Sede::factory()->almacen()->create();

    $tienda = Sede::factory()->tienda()->create([
        'almacen_id' => $almacen->id,
    ]);

    expect($tienda)->toBeInstanceOf(Sede::class)
        ->and($tienda->tipo)->toBe('tienda')
        ->and($tienda->almacen_id)->toBe($almacen->id)
        ->and($tienda->esAlmacen())->toBeFalse()
        ->and($tienda->almacen->id)->toBe($almacen->id);

    $this->assertDatabaseHas('sedes', [
        'id' => $tienda->id,
        'tipo' => 'tienda',
        'almacen_id' => $almacen->id,
    ]);
});

test('relacion de almacen con sus tiendas asociadas funciona correctamente', function () {
    $almacen = Sede::factory()->almacen()->create();

    $tienda1 = Sede::factory()->tienda()->create(['almacen_id' => $almacen->id]);
    $tienda2 = Sede::factory()->tienda()->create(['almacen_id' => $almacen->id]);

    expect($almacen->tiendas)->toHaveCount(2)
        ->and($almacen->tiendas->pluck('id')->all())->toEqualCanonicalizing([$tienda1->id, $tienda2->id]);
});
