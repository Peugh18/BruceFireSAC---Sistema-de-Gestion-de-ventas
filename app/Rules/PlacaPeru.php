<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Placa de 6 letras o números, con o sin guion (ABC-123, A1B-234, 1234-AB).
 */
class PlacaPeru implements ValidationRule
{
    public const MENSAJE = 'La placa tiene 6 letras o números, por ejemplo ABC-123.';

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $placa = (string) $value;

        if (substr_count($placa, '-') > 1 || preg_match('/^[A-Z0-9]{6}$/', str_replace('-', '', $placa)) !== 1) {
            $fail(self::MENSAJE);
        }
    }
}
