<?php

namespace App\Http\Requests\Cobranzas;

use Illuminate\Foundation\Http\FormRequest;

class StoreDetraccionRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['numero_operacion' => strtoupper(preg_replace('/\s+/u', '', (string) $this->input('numero_operacion')) ?? '')]);
    }

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
            'fecha_deposito' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'evidencia' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ];
    }
}
