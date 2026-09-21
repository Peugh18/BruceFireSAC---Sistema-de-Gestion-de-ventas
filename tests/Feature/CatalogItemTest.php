<?php

use App\Models\CatalogItem;
use Illuminate\Database\QueryException;

test('codigo duplicado debe fallar por constraint de base de datos', function () {
    CatalogItem::factory()->create([
        'codigo' => 'PRD-TEST-001',
    ]);

    $this->expectException(QueryException::class);

    CatalogItem::factory()->create([
        'codigo' => 'PRD-TEST-001',
    ]);
});

test('esServicio retorna false cuando el tipo es producto', function () {
    $item = CatalogItem::factory()->producto()->create();

    expect($item->tipo)->toBe('producto')
        ->and($item->esServicio())->toBeFalse();
});

test('esServicio retorna true cuando el tipo es servicio', function () {
    $item = CatalogItem::factory()->servicio()->create();

    expect($item->tipo)->toBe('servicio')
        ->and($item->esServicio())->toBeTrue();
});
