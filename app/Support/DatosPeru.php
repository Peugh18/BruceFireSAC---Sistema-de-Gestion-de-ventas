<?php

namespace App\Support;

/**
 * Limpia lo que escribe el usuario antes de validarlo: quita espacios, guiones,
 * paréntesis y el +51, para que «999 999 999» y «(+51) 999-999-999» sean lo mismo.
 */
class DatosPeru
{
    public static function digitos(mixed $valor): ?string
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        return preg_replace('/\D/', '', (string) $valor);
    }

    /**
     * Teléfono sin el código de país: solo dígitos.
     */
    public static function telefono(mixed $valor): ?string
    {
        $digitos = self::digitos($valor);

        if ($digitos === null || $digitos === '') {
            return null;
        }

        if (preg_match('/^51(9\d{8}|[1-8]\d{7})$/', $digitos) === 1) {
            return substr($digitos, 2);
        }

        return $digitos;
    }

    public static function placa(mixed $valor): ?string
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        return strtoupper(preg_replace('/[\s.]/', '', (string) $valor));
    }

    public static function licencia(mixed $valor): ?string
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        return strtoupper(preg_replace('/[\s-]/', '', (string) $valor));
    }
}
