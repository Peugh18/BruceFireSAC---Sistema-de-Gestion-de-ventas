<?php

namespace App\Http\Requests\Clientes;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreVehicleRequest extends FormRequest
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
            'placa' => ['required', 'string', 'max:255', Rule::unique('vehicles', 'placa')],
            'marca' => ['nullable', 'string', 'max:255'],
            'modelo' => ['nullable', 'string', 'max:255'],
            'descripcion' => ['nullable', 'string', 'max:255'],
            'estado' => ['sometimes', Rule::in(['activo', 'inactivo'])],
        ];
    }
}
