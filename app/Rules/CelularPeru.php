<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Celular peruano: 9 dígitos y empieza con 9. El valor llega ya limpio.
 */
class CelularPeru implements ValidationRule
{
    public const MENSAJE = 'Escribe un celular de 9 dígitos que empiece con 9.';

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (preg_match('/^9\d{8}$/', (string) $value) !== 1) {
            $fail(self::MENSAJE);
        }
    }
}
