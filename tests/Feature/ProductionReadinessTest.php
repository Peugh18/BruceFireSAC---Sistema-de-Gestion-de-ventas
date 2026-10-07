<?php

use Illuminate\Support\Facades\Artisan;

test('respuestas web incluyen cabeceras basicas de seguridad', function () {
    $this->get('/login')->assertHeader('X-Frame-Options', 'SAMEORIGIN')->assertHeader('X-Content-Type-Options', 'nosniff')->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
});

test('la CSP lleva el nonce del script en línea y HSTS solo va por https', function () {
    $respuesta = $this->get('/login');
    $csp = (string) $respuesta->headers->get('Content-Security-Policy-Report-Only');

    preg_match("/'nonce-([^']+)'/", $csp, $nonce);

    expect($csp)->toContain("default-src 'self'")->toContain("object-src 'none'")
        ->and($respuesta->getContent())->toContain('nonce="'.$nonce[1].'"')
        ->and($respuesta->headers->has('Strict-Transport-Security'))->toBeFalse();

    config(['seguridad.csp_solo_reporte' => false]);

    $this->get('https://localhost/login')
        ->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains')
        ->assertHeaderMissing('Content-Security-Policy-Report-Only')
        ->assertHeader('Content-Security-Policy');
});

test('comando de respaldo crea una copia de la base mysql', function () {
    $antes = glob(storage_path('app/backups/bd_*.sql')) ?: [];

    expect(Artisan::call('backup:bd'))->toBe(0);

    $nuevos = array_diff(glob(storage_path('app/backups/bd_*.sql')) ?: [], $antes);
    expect($nuevos)->not->toBeEmpty();

    // La prueba no deja respaldos sueltos.
    array_map('unlink', $nuevos);
});
