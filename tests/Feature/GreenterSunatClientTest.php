<?php

use App\Services\Billing\GreenterSunatClient;
use Greenter\Ws\Services\SunatEndpoints;

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
