<?php

namespace App\Http\Requests\Gerente;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCompanySettingRequest extends FormRequest
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
            'razon_social' => ['required', 'string', 'max:255'],
            'nombre_comercial' => ['nullable', 'string', 'max:255'],
            'ruc' => ['required', 'digits:11'],
            'direccion' => ['nullable', 'string', 'max:255'],
            'ubigeo' => ['nullable', 'string', 'max:6'],
            'departamento' => ['nullable', 'string', 'max:255'],
            'provincia' => ['nullable', 'string', 'max:255'],
            'distrito' => ['nullable', 'string', 'max:255'],
            'telefono' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'leyenda_pie' => ['nullable', 'string', 'max:500'],
            'cuenta_detraccion' => ['nullable', 'string', 'max:50'],
            'logo' => ['nullable', 'image', 'max:2048'],
        ];
    }
}
