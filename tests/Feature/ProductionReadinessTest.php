<?php

use Illuminate\Support\Facades\Artisan;

test('respuestas web incluyen cabeceras basicas de seguridad', function () {
    $this->get('/login')->assertHeader('X-Frame-Options', 'SAMEORIGIN')->assertHeader('X-Content-Type-Options', 'nosniff')->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
});

test('comando de respaldo crea una copia sqlite', function () {
    $database = storage_path('framework/testing-backup.sqlite');
    touch($database);
    config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => $database]);
    expect(Artisan::call('backup:bd'))->toBe(0);
    expect(collect(glob(storage_path('app/backups/bd_*.sqlite')) ?: [])->isNotEmpty())->toBeTrue();
});
