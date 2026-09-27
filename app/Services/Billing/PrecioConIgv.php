<?php

namespace App\Services\Billing;

class PrecioConIgv
{
    public const TASA = 0.18;

    /**
     * Los precios de Bruce Fire ya incluyen IGV (S/ 80 cobrados = 67.80 de
     * base + 12.20 de IGV). Separa un monto con IGV en base e IGV sin perder
     * céntimos: base redondeada y el IGV es la diferencia.
     *
     * @return array{base: float, igv: float, total: float}
     */
    public static function desglosar(float $totalConIgv): array
    {
        $total = round($totalConIgv, 2);
        $base = round($total / (1 + self::TASA), 2);

        return ['base' => $base, 'igv' => round($total - $base, 2), 'total' => $total];
    }

    /**
     * Totales de un comprobante sumando el desglose de cada línea, para que
     * la cabecera cuadre exactamente con el detalle que se envía a SUNAT.
     *
     * @param  iterable<float>  $totalesDeLinea
     * @return array{subtotal: float, igv: float, total: float}
     */
    public static function totales(iterable $totalesDeLinea): array
    {
        $subtotal = 0.0;
        $igv = 0.0;
        $total = 0.0;

        foreach ($totalesDeLinea as $totalLinea) {
            $linea = self::desglosar((float) $totalLinea);
            $subtotal += $linea['base'];
            $igv += $linea['igv'];
            $total += $linea['total'];
        }

        return ['subtotal' => round($subtotal, 2), 'igv' => round($igv, 2), 'total' => round($total, 2)];
    }
}
