<?php

use App\Services\Certificates\CertificateDateCalculator;
use App\Services\Certificates\CertificateRuleEngine;
use Carbon\Carbon;

test('proximaPruebaHidrostatica suma exactamente 5 anos', function () {
    $fecha = Carbon::parse('2026-05-15');
    $proxima = CertificateDateCalculator::proximaPruebaHidrostatica($fecha);

    expect($proxima->toDateString())->toBe('2031-05-15');
});

test('proximaOperatividad suma exactamente 1 ano', function () {
    $fecha = Carbon::parse('2026-05-15');
    $proxima = CertificateDateCalculator::proximaOperatividad($fecha);

    expect($proxima->toDateString())->toBe('2027-05-15');
});

test('determinarTipos para venta nueva en vehiculo sin ph realizada incluye operatividad garantia y no prueba hidrostatica', function () {
    $engine = new CertificateRuleEngine;

    $tipos = $engine->determinarTipos([
        'servicio_realizado' => 'venta_nueva',
        'tipo_equipo' => 'extintor_pqs',
        'destino' => 'vehiculo',
        'ph_realizada' => false,
    ]);

    expect($tipos)->toContain('operatividad_garantia')
        ->and($tipos)->not->toContain('prueba_hidrostatica');
});

test('determinarTipos para destino local con capacitacion realizada incluye operatividad garantia y capacitacion', function () {
    $engine = new CertificateRuleEngine;

    $tipos = $engine->determinarTipos([
        'servicio_realizado' => 'recarga',
        'tipo_equipo' => 'extintor_pqs',
        'destino' => 'local',
        'capacitacion_realizada' => true,
    ]);

    expect($tipos)->toContain('operatividad_garantia')
        ->and($tipos)->toContain('capacitacion');
});

test('determinarTipos para destino vehiculo con ph realizada incluye operatividad garantia y prueba hidrostatica', function () {
    $engine = new CertificateRuleEngine;

    $tipos = $engine->determinarTipos([
        'servicio_realizado' => 'recarga',
        'tipo_equipo' => 'extintor_pqs',
        'destino' => 'vehiculo',
        'ph_realizada' => true,
    ]);

    expect($tipos)->toContain('operatividad_garantia')
        ->and($tipos)->toContain('prueba_hidrostatica');
});

test('determinarTipos para sistema deteccion devuelve solo informe deteccion ignorando otros campos', function () {
    $engine = new CertificateRuleEngine;

    $tipos = $engine->determinarTipos([
        'servicio_realizado' => 'venta_nueva',
        'tipo_equipo' => 'sistema_deteccion',
        'destino' => 'vehiculo',
        'ph_realizada' => true,
        'capacitacion_realizada' => true,
    ]);

    expect($tipos)->toBe(['informe_deteccion']);
});

test('determinarTipos para lamina seguridad devuelve solo lamina seguridad', function () {
    $engine = new CertificateRuleEngine;

    $tipos = $engine->determinarTipos([
        'servicio_realizado' => 'instalacion',
        'tipo_equipo' => 'lamina_seguridad',
        'destino' => 'local',
    ]);

    expect($tipos)->toBe(['lamina_seguridad']);
});
