<?php

use App\Services\Billing\GreenterService;
use App\Services\Billing\GreenterSunatClient;
use Greenter\Model\Sale\Invoice;
use Greenter\Ws\Services\SunatEndpoints;
use Illuminate\Validation\ValidationException;

/**
 * Lee lo que el SoapClient tiene configurado, sin conectarse a SUNAT.
 *
 * @return array{location: string, ssl: array<string, mixed>}
 */
function configuracionSoap(SoapClient $client): array
{
    $contexto = (new ReflectionProperty(SoapClient::class, '_stream_context'))->getValue($client);

    return [
        'location' => (new ReflectionProperty(SoapClient::class, 'location'))->getValue($client),
        'ssl' => stream_context_get_options($contexto)['ssl'] ?? [],
    ];
}

test('el cliente SOAP verifica el certificado TLS de SUNAT', function () {
    $ssl = configuracionSoap((new GreenterSunatClient)->soapClient())['ssl'];

    expect($ssl['verify_peer'] ?? null)->toBeTrue()
        ->and($ssl['verify_peer_name'] ?? null)->toBeTrue()
        ->and($ssl)->not->toHaveKey('allow_self_signed');
});

test('en beta el cliente SOAP apunta al servidor de pruebas de SUNAT', function () {
    config(['billing.sunat.beta' => true]);

    expect(configuracionSoap((new GreenterSunatClient)->soapClient())['location'])->toBe(SunatEndpoints::FE_BETA);
});

test('fuera de beta el cliente SOAP apunta al servidor de producción de SUNAT', function () {
    config(['billing.sunat.beta' => false]);

    expect(configuracionSoap((new GreenterSunatClient)->soapClient())['location'])->toBe(SunatEndpoints::FE_PRODUCCION);
});

test('un certificado SUNAT ausente muestra una validacion comprensible', function () {
    config(['billing.sunat.cert_path' => storage_path('certificado-inexistente.pem')]);

    try {
        app(GreenterService::class)->sign(new Invoice);
        $this->fail('Debia informar el problema de configuracion.');
    } catch (ValidationException $exception) {
        expect($exception->errors()['sunat'][0])->toContain('certificado SUNAT');
    }
});

test('un archivo que no es certificado SUNAT muestra una validacion comprensible', function () {
    config(['billing.sunat.cert_path' => __FILE__]);

    try {
        app(GreenterService::class)->sign(new Invoice);
        $this->fail('Debia informar el problema de configuracion.');
    } catch (ValidationException $exception) {
        expect($exception->errors()['sunat'][0])->toContain('certificado SUNAT');
    }
});
