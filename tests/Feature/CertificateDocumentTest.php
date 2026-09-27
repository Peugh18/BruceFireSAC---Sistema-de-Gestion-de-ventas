<?php

use App\Actions\Certificates\IssueCertificate;
use App\Models\Certificate;
use App\Models\CertificateType;
use App\Models\Client;
use App\Services\Certificates\CertificateDocumentData;
use App\Services\Certificates\CertificatePdfService;
use Database\Seeders\CertificateTypeSeeder;

beforeEach(function () {
    $this->travelTo(now()->setDate(2026, 9, 26)->setTime(10, 0));
    $this->seed(CertificateTypeSeeder::class);
});

/**
 * @param  array<string, mixed>  $extra
 */
function emitirCertificado(string $codigo, array $extra = []): Certificate
{
    $tipo = CertificateType::where('codigo', $codigo)->firstOrFail();

    return app(IssueCertificate::class)->handle($tipo, Client::factory()->create(), [], extra: $extra);
}

function hojasDelPdf(string $pdf): int
{
    return preg_match_all('#/Type\s*/Page(?!s)#', $pdf);
}

test('the certificate is drawn with the template it was issued with even after the type changes', function () {
    $certificado = emitirCertificado('luces_emergencia');

    CertificateType::where('codigo', 'luces_emergencia')->update(['subtitulo' => 'LUCES LED', 'version' => 2]);

    $documento = app(CertificateDocumentData::class)->desde($certificado->fresh());

    expect($documento['tipo']['subtitulo'])->toBe('LUCES DE EMERGENCIA')
        ->and(array_column($documento['firmantes'], 'nombre'))->toBe(['Edgar Guevara Cabrera', 'Ing. Sixto Leiva Marín']);
});

test('a measured value outside the template range fails the test and leaves the result observed', function () {
    $certificado = emitirCertificado('luces_emergencia', ['datos' => [
        'filas' => [['item' => '01', 'ubicacion' => 'Recepción', 'resultado' => 'operativo']],
        'pruebas' => [1 => ['estado' => 'C', 'valor' => '8'], 4 => ['estado' => 'C', 'valor' => '1']],
    ]]);

    $documento = app(CertificateDocumentData::class)->desde($certificado);

    expect($documento['pruebas'][1])->toMatchArray(['estado' => 'C', 'valor' => '8 s'])
        ->and($documento['pruebas'][4])->toMatchArray(['estado' => 'NC', 'valor' => '1 h'])
        ->and($documento['resultado'])->toBe('observado');
});

test('an annulled certificate says so on the document with its reason', function () {
    $certificado = emitirCertificado('luces_emergencia');
    $certificado->update(['estado' => 'anulado', 'anulado_motivo' => 'RUC del cliente equivocado', 'anulado_at' => now()]);

    $documento = app(CertificateDocumentData::class)->desde($certificado);

    expect($documento['certificado']['estado'])->toBe(['clave' => 'anulado', 'texto' => 'ANULADO', 'fecha' => '26/09/2026'])
        ->and($documento['certificado']['anulado_motivo'])->toBe('RUC del cliente equivocado')
        ->and(app(CertificatePdfService::class)->generate($certificado)->output())->toStartWith('%PDF');
});

test('the vehicle plate is shown as its own field', function () {
    $certificado = emitirCertificado('operatividad_garantia', ['referencia' => 'PLACA: B32-928']);

    expect(app(CertificateDocumentData::class)->desde($certificado)['cliente']['referencia'])
        ->toBe(['etiqueta' => 'Placa', 'valor' => 'B32-928']);
});

test('the sample of a service certificate fits in a single page', function (string $codigo) {
    $pdf = app(CertificatePdfService::class)->muestra(CertificateType::where('codigo', $codigo)->firstOrFail())->output();

    expect($pdf)->toStartWith('%PDF')
        ->and(hojasDelPdf($pdf))->toBe(1);
})->with(['luces_emergencia', 'operatividad_garantia']);
