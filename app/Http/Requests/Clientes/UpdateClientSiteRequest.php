<?php

namespace App\Http\Requests\Clientes;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateClientSiteRequest extends FormRequest
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
            'tipo' => ['required', Rule::in(['oficina', 'tienda', 'planta', 'almacen', 'local', 'sucursal', 'otra'])],
            'nombre' => ['required', 'string', 'max:255'],
            'direccion' => ['required', 'string', 'max:255'],
            'ubigeo' => ['nullable', 'string', 'size:6', 'exists:ubigeos,codigo'],
            'referencia' => ['nullable', 'string', 'max:255'],
            'contacto' => ['nullable', 'string', 'max:255'],
            'telefono' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'estado' => ['sometimes', Rule::in(['activo', 'inactivo'])],
        ];
    }
}
