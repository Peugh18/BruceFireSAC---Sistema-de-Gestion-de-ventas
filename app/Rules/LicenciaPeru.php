<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Licencia de conducir: una letra y 8 números (Q12345678).
 */
class LicenciaPeru implements ValidationRule
{
    public const MENSAJE = 'La licencia tiene una letra y 8 números, por ejemplo Q12345678.';

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (preg_match('/^[A-Z]\d{8}$/', (string) $value) !== 1) {
            $fail(self::MENSAJE);
        }
    }
}
