<?php

use Illuminate\Support\Facades\Artisan;

test('respuestas web incluyen cabeceras basicas de seguridad', function () {
    $this->get('/login')->assertHeader('X-Frame-Options', 'SAMEORIGIN')->assertHeader('X-Content-Type-Options', 'nosniff')->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
});

test('comando de respaldo crea una copia de la base mysql', function () {
    $antes = glob(storage_path('app/backups/bd_*.sql')) ?: [];

    expect(Artisan::call('backup:bd'))->toBe(0);

    $nuevos = array_diff(glob(storage_path('app/backups/bd_*.sql')) ?: [], $antes);
    expect($nuevos)->not->toBeEmpty();

    // La prueba no deja respaldos sueltos.
    array_map('unlink', $nuevos);
});
