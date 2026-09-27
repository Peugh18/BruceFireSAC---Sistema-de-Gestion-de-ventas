<?php

use App\Models\Certificate;
use App\Models\CertificateType;
use App\Models\CertificateUnit;
use App\Models\Client;

function certificadoParaVerificar(array $atributos = []): Certificate
{
    $tipo = CertificateType::factory()->create([
        'codigo' => 'operatividad_garantia',
        'nombre' => 'Operatividad y Garantía',
        'vigencia_meses' => 12,
    ]);

    return Certificate::factory()->create([
        'certificate_type_id' => $tipo->id,
        'client_id' => Client::factory()->create(['razon_social' => 'COMERCIAL LOS ANDES S.A.C.'])->id,
        ...$atributos,
    ]);
}

test('scanning the qr shows the inspector a valid certificate page without logging in', function () {
    $certificate = certificadoParaVerificar(['fecha_vigencia_hasta' => now()->addMonths(6)->toDateString()]);
    CertificateUnit::factory()->create(['certificate_id' => $certificate->id, 'numero_serie_snapshot' => 'SN-00543']);

    $response = $this->get(route('certificados.verificar', ['token' => $certificate->qr_token]));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('publico/verificar-certificado')
        ->where('certificado.numero', $certificate->numero)
        ->where('certificado.estado', 'vigente')
        ->where('certificado.cliente.razon_social', 'COMERCIAL LOS ANDES S.A.C.')
        ->where('certificado.equipos.0.serie', 'SN-00543')
        ->where('certificado.pdf_url', route('certificados.verificar.pdf', ['token' => $certificate->qr_token]))
    );
});

test('a certificate past its date shows as expired even if the nightly job has not marked it', function () {
    $certificate = certificadoParaVerificar([
        'estado' => 'vigente',
        'fecha_vigencia_hasta' => now()->subDay()->toDateString(),
    ]);

    $this->get(route('certificados.verificar', ['token' => $certificate->qr_token]))
        ->assertInertia(fn ($page) => $page->where('certificado.estado', 'vencido'));
});

test('a replaced certificate is not shown as valid', function () {
    $certificate = certificadoParaVerificar(['estado' => 'reemplazado']);

    $this->get(route('certificados.verificar', ['token' => $certificate->qr_token]))
        ->assertInertia(fn ($page) => $page->where('certificado.estado', 'reemplazado'));
});

test('a training certificate without expiry date is shown as valid', function () {
    $certificate = certificadoParaVerificar(['fecha_vigencia_hasta' => null]);

    $this->get(route('certificados.verificar', ['token' => $certificate->qr_token]))
        ->assertInertia(fn ($page) => $page
            ->where('certificado.estado', 'sin_vencimiento')
            ->where('certificado.fecha_vencimiento', null)
        );
});

test('an unknown qr token shows the not found verification page with 404', function () {
    $response = $this->get(route('certificados.verificar', ['token' => 'token-que-no-existe']));

    $response->assertNotFound();
    $response->assertInertia(fn ($page) => $page
        ->component('publico/verificar-certificado')
        ->where('certificado', null)
    );
});

test('the dni of a natural person is partially hidden on the public page', function () {
    $certificate = certificadoParaVerificar([
        'client_id' => Client::factory()->dni()->create(['numero_documento' => '75359392'])->id,
    ]);

    $this->get(route('certificados.verificar', ['token' => $certificate->qr_token]))
        ->assertInertia(fn ($page) => $page
            ->where('certificado.cliente.documento_tipo', 'DNI')
            ->where('certificado.cliente.documento', '75••••92')
        );
});

test('the inspector can open the original pdf from the qr page', function () {
    $certificate = certificadoParaVerificar();

    $response = $this->get(route('certificados.verificar.pdf', ['token' => $certificate->qr_token]));

    $response->assertOk();
    $response->assertHeader('Content-Type', 'application/pdf');
});

test('the original pdf is not available for an unknown token', function () {
    $this->get(route('certificados.verificar.pdf', ['token' => 'token-que-no-existe']))
        ->assertNotFound();
});
