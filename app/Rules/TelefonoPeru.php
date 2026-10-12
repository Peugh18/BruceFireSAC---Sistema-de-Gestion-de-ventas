<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Celular (9 dígitos, empieza con 9) o fijo con código de ciudad
 * (8 dígitos, o 9 con el 0 inicial). El valor llega ya limpio.
 */
class TelefonoPeru implements ValidationRule
{
    public const MENSAJE = 'Escribe un celular de 9 dígitos que empiece con 9, o un fijo con código de ciudad.';

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (preg_match('/^(9\d{8}|0?[1-8]\d{7})$/', (string) $value) !== 1) {
            $fail(self::MENSAJE);
        }
    }
}
