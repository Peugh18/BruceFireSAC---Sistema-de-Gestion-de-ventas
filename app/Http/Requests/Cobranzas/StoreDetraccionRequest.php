<?php

namespace App\Http\Requests\Cobranzas;

use Illuminate\Foundation\Http\FormRequest;

class StoreDetraccionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'monto' => ['required', 'numeric', 'min:0.01', 'decimal:0,2', 'max:9999999.99'],
            'numero_operacion' => ['required', 'string', 'max:255'],
        ];
    }
}
