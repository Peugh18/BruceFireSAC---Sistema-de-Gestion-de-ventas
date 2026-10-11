<?php

namespace App\Services\Billing;

use App\Contracts\GreClientInterface;
use Greenter\Zip\ZipFly;

/**
 * Cliente de guías que NO sale a SUNAT: acepta toda guía y devuelve un CDR de
 * mentira. Solo sirve para recorrer en una PC de desarrollo el flujo «sin CDR
 * no sale la mercadería» (despachar y recibir traslados). Nunca se activa en
 * producción: ver AppServiceProvider.
 */
class GreSimuladoClient implements GreClientInterface
{
    public const MENSAJE = 'SIMULADO: guía aceptada solo en este entorno local; no se envió a SUNAT y no tiene validez.';

    public function enviar(string $nombreArchivo, string $zip): string
    {
        return 'SIM-'.substr(hash('sha256', $nombreArchivo), 0, 16);
    }

    public function consultar(string $ticket): array
    {
        $cdr = (string) (new ZipFly)->compress("R-{$ticket}.xml", '<ApplicationResponse>'.self::MENSAJE.'</ApplicationResponse>');

        return ['en_proceso' => false, 'aceptada' => true, 'cdr_zip' => $cdr, 'codigo' => '0', 'mensaje' => self::MENSAJE];
    }
}
