<?php

use App\Actions\Certificates\IssueCertificate;
use App\Models\CertificateType;
use App\Models\Client;
use App\Models\Service;
use App\Services\Certificates\CertificateNumberGenerator;
use Database\Seeders\CertificateTypeSeeder;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->travelTo(now()->setDate(2026, 9, 26)->setTime(10, 0));
    $this->seed(CertificateTypeSeeder::class);
});

function emitir(string $codigo): string
{
    $tipo = CertificateType::where('codigo', $codigo)->firstOrFail();

    return app(IssueCertificate::class)->handle($tipo, Client::factory()->create(), [])->numero;
}

test('a continuous series keeps going from the number the company already uses on paper', function () {
    expect(emitir('luces_emergencia'))->toBe('LM-0006540')
        ->and(emitir('luces_emergencia'))->toBe('LM-0006541')
        ->and(emitir('informe_deteccion'))->toBe('SDA-125426');
});

test('a yearly series restarts at 0001 in the next year', function () {
    expect(emitir('operatividad_garantia'))->toBe('OG-2026-0001')
        ->and(emitir('operatividad_garantia'))->toBe('OG-2026-0002');

    $this->travelTo(now()->setDate(2027, 1, 2));

    expect(emitir('operatividad_garantia'))->toBe('OG-2027-0001');
});

test('a number is never reused even if its certificate is deleted', function () {
    $tipo = CertificateType::where('codigo', 'operatividad_garantia')->firstOrFail();
    $primero = app(IssueCertificate::class)->handle($tipo, Client::factory()->create(), []);
    $primero->delete();

    expect(emitir('operatividad_garantia'))->toBe('OG-2026-0002');
});

test('the series cannot be moved back to a number already used', function () {
    emitir('luces_emergencia');
    $tipo = CertificateType::where('codigo', 'luces_emergencia')->firstOrFail();

    app(CertificateNumberGenerator::class)->continuarDesde($tipo, 6540);
})->throws(ValidationException::class);

test('each certificate keeps the template version it was issued with', function () {
    $tipo = CertificateType::where('codigo', 'luces_emergencia')->firstOrFail();
    $viejo = app(IssueCertificate::class)->handle($tipo, Client::factory()->create(), []);

    $tipo->update(['subtitulo' => 'LUCES DE EMERGENCIA LED', 'version' => 2]);
    $nuevo = app(IssueCertificate::class)->handle($tipo->fresh(), Client::factory()->create(), []);

    expect($viejo->typeVersion->configuracion['subtitulo'])->toBe('LUCES DE EMERGENCIA')
        ->and($nuevo->typeVersion->configuracion['subtitulo'])->toBe('LUCES DE EMERGENCIA LED')
        ->and($viejo->typeVersion->configuracion['firmantes'][1]['cip'])->toBe('218485');
});

test('a service can say which certificate it generates', function () {
    $tipo = CertificateType::where('codigo', 'luces_emergencia')->firstOrFail();
    $servicio = Service::factory()->create(['certificate_type_id' => $tipo->id]);

    expect($servicio->certificateType->codigo)->toBe('luces_emergencia')
        ->and($tipo->services->pluck('id')->all())->toBe([$servicio->id]);
});

test('seeded certificate types carry the signers of the real certificates in order', function () {
    $luces = CertificateType::where('codigo', 'luces_emergencia')->firstOrFail();

    expect($luces->signers->pluck('nombre')->all())->toBe(['Edgar Guevara Cabrera', 'Ing. Sixto Leiva Marín'])
        ->and($luces->signers[1]->cip)->toBe('218485')
        ->and($luces->checklist)->toHaveCount(10)
        ->and(app(CertificateNumberGenerator::class)->proximo($luces))->toBe('LM-0006540');
});
