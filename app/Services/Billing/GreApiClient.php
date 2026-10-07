<?php

namespace App\Services\Billing;

use App\Contracts\GreClientInterface;
use Greenter\Sunat\GRE\Api\AuthApi;
use Greenter\Sunat\GRE\Api\CpeApi;
use Greenter\Sunat\GRE\ApiException;
use Greenter\Sunat\GRE\Configuration;
use Greenter\Sunat\GRE\Model\CpeDocument;
use Greenter\Sunat\GRE\Model\CpeDocumentArchivo;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

/**
 * Envía las guías de remisión por la API REST de SUNAT con greenter/gre-api:
 * token OAuth (dura 1 hora y se reutiliza), envío con hash, ticket y consulta
 * hasta tener el CDR. Guzzle verifica el certificado TLS del servidor.
 */
class GreApiClient implements GreClientInterface
{
    /**
     * Código con el que se marca una consulta sin respuesta útil: no dice nada
     * sobre el destino de la guía y por lo tanto no la rechaza.
     */
    public const SIN_RESPUESTA = 'sin_respuesta';

    public function enviar(string $nombreArchivo, string $zip): string
    {
        $documento = (new CpeDocument)->setArchivo(
            (new CpeDocumentArchivo)
                ->setNomArchivo($nombreArchivo.'.zip')
                ->setArcGreZip(base64_encode($zip))
                ->setHashZip(hash('sha256', $zip))
        );

        $respuesta = $this->cpeApi()->enviarCpe($nombreArchivo, $documento);
        $ticket = $respuesta->getNumTicket();

        if (! is_string($ticket) || $ticket === '') {
            throw new RuntimeException('SUNAT no devolvió un ticket para la guía.');
        }

        return $ticket;
    }

    public function consultar(string $ticket): array
    {
        try {
            $estado = $this->cpeApi()->consultarEnvio($ticket);
        } catch (ApiException $e) {
            // Un error de comunicación no es un rechazo: no se sabe qué pasó con
            // la guía y se vuelve a consultar. Solo el código 99 la rechaza.
            return ['en_proceso' => false, 'aceptada' => false, 'cdr_zip' => null, 'codigo' => self::SIN_RESPUESTA, 'mensaje' => 'SUNAT no respondió al consultar el ticket.'];
        }

        $codigo = (string) $estado->getCodRespuesta();

        if ($codigo === '98') {
            return ['en_proceso' => true, 'aceptada' => false, 'cdr_zip' => null, 'codigo' => '98', 'mensaje' => 'SUNAT aún está procesando el ticket.'];
        }

        $cdr = $estado->getArcCdr();
        $cdrZip = is_string($cdr) && $cdr !== '' ? base64_decode($cdr, true) : false;

        if ($codigo === '0' && $cdrZip !== false) {
            return ['en_proceso' => false, 'aceptada' => true, 'cdr_zip' => $cdrZip, 'codigo' => '0', 'mensaje' => 'La guía fue aceptada por SUNAT.'];
        }

        $error = $estado->getError();

        return [
            'en_proceso' => false,
            'aceptada' => false,
            'cdr_zip' => $cdrZip === false ? null : $cdrZip,
            // Sin código no hay rechazo que valga: queda sin respuesta.
            'codigo' => $codigo === '' ? self::SIN_RESPUESTA : $codigo,
            'mensaje' => $error?->getDesError() ?: 'SUNAT no aceptó la guía.',
        ];
    }

    protected function cpeApi(): CpeApi
    {
        $config = Configuration::getDefaultConfiguration()
            ->setAccessToken($this->token())
            ->setHost((string) config('billing.gre.api_host'));

        return new CpeApi(new Client, $config);
    }

    protected function token(): string
    {
        $clientId = config('billing.sunat.client_id');
        $secret = config('billing.sunat.client_secret');

        if (! is_string($clientId) || $clientId === '' || ! is_string($secret) || $secret === '') {
            throw new RuntimeException('Faltan las credenciales de la API de guías (SUNAT_GRE_CLIENT_ID y SUNAT_GRE_CLIENT_SECRET).');
        }

        return Cache::remember('gre.token.'.$clientId, now()->addMinutes(55), function () use ($clientId, $secret): string {
            $auth = new AuthApi(new Client, Configuration::getDefaultConfiguration()->setHost((string) config('billing.gre.auth_host')));
            $token = $auth->getToken(
                'password',
                'https://api-cpe.sunat.gob.pe',
                $clientId,
                $secret,
                config('billing.sunat.ruc').config('billing.sunat.usuario_sol'),
                (string) config('billing.sunat.clave_sol'),
            );

            return (string) $token->getAccessToken();
        });
    }
}
