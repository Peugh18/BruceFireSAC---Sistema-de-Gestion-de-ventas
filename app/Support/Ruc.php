<?php

namespace App\Support;

/**
 * RUC peruano: 11 dígitos, empieza con 10 (persona natural), 15, 16, 17 o 20
 * (empresa) y su último dígito es el verificador (módulo 11 de SUNAT).
 */
class Ruc
{
    public static function esValido(string $ruc): bool
    {
        if (preg_match('/^(10|15|16|17|20)\d{9}$/', $ruc) !== 1) {
            return false;
        }

        $pesos = [5, 4, 3, 2, 7, 6, 5, 4, 3, 2];
        $suma = 0;

        foreach ($pesos as $i => $peso) {
            $suma += (int) $ruc[$i] * $peso;
        }

        $digito = 11 - ($suma % 11);
        $digito = match ($digito) {
            10 => 0,
            11 => 1,
            default => $digito,
        };

        return (int) $ruc[10] === $digito;
    }
}
