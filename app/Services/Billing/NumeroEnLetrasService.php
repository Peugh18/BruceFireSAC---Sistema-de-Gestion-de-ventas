<?php

namespace App\Services\Billing;

use NumberFormatter;

class NumeroEnLetrasService
{
    /**
     * Convierte un monto a la leyenda "SON ... CON NN/100 SOLES" exigida por
     * SUNAT (Catálogo 52, código 1000) y usada también en la representación
     * impresa del comprobante.
     */
    public function convertir(float $monto, string $moneda = 'SOLES'): string
    {
        $entero = (int) floor($monto);
        $centavos = (int) round(($monto - $entero) * 100);

        $formatter = new NumberFormatter('es', NumberFormatter::SPELLOUT);
        $letras = mb_strtoupper((string) $formatter->format($entero));

        return sprintf('SON %s CON %02d/100 %s', $letras, $centavos, $moneda);
    }
}
