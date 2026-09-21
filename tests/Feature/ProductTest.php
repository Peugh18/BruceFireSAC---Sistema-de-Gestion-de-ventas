<?php

use App\Models\Product;
use Illuminate\Database\QueryException;

test('codigo duplicado de producto debe fallar por constraint de base de datos', function () {
    Product::factory()->create([
        'codigo' => 'PRD-TEST-001',
    ]);

    $this->expectException(QueryException::class);

    Product::factory()->create([
        'codigo' => 'PRD-TEST-001',
    ]);
});

test('esServicio retorna false para productos', function () {
    $item = Product::factory()->create();

    expect($item->esServicio())->toBeFalse()
        ->and($item->serializado)->toBeTrue()
        ->and($item->stock_minimo)->toBe(5);
});
