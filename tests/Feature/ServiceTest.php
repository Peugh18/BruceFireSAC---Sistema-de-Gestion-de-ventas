<?php

use App\Models\Service;
use Illuminate\Database\QueryException;

test('codigo duplicado de servicio debe fallar por constraint de base de datos', function () {
    Service::factory()->create([
        'codigo' => 'SRV-TEST-001',
    ]);

    $this->expectException(QueryException::class);

    Service::factory()->create([
        'codigo' => 'SRV-TEST-001',
    ]);
});

test('esServicio retorna true para servicios', function () {
    $service = Service::factory()->create();

    expect($service->esServicio())->toBeTrue()
        ->and($service->unidad_medida)->toBe('ZZ');
});
