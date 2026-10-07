<?php

namespace App\Http\Requests\Deficiencies;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDeficiencyAuthorizationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'autorizado' => ['required', 'boolean'],
            'autorizado_por' => ['required', 'string', 'max:255'],
            'canal' => ['required', Rule::in(['whatsapp', 'presencial'])],
            'fecha' => ['required', 'date'],
            'observacion' => ['nullable', 'string'],
            'cotizacion_adicional_id' => ['nullable', 'integer', 'exists:quotes,id'],
            // V6: lo que el cliente aceptó pagar por el adicional.
            'importe' => ['nullable', 'numeric', 'min:0.01', 'max:999999'],
        ];
    }
}
