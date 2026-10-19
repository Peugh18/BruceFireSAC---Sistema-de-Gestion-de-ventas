<?php

namespace App\Http\Requests\Cash;

use Illuminate\Foundation\Http\FormRequest;

class StoreSalidaDeCajaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'monto' => ['required', 'numeric', 'gt:0', 'max:99999'],
            'motivo' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'monto.gt' => 'El monto debe ser mayor a cero.',
            'motivo.required' => 'Escribe para qué sale el dinero.',
        ];
    }
}
