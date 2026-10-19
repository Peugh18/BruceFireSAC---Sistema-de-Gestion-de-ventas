<?php

namespace App\Http\Requests\Certificates;

use Illuminate\Foundation\Http\FormRequest;

class CorregirCertificadoRequest extends FormRequest
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
            'motivo' => ['required', 'string', 'max:255'],
            'direccion' => ['nullable', 'string', 'max:255'],
            'referencia' => ['nullable', 'string', 'max:255'],
            'unidades' => ['present', 'array'],
            'unidades.*.id' => ['required', 'integer'],
            'unidades.*.numero_serie' => ['nullable', 'string', 'max:100'],
            'unidades.*.marca' => ['nullable', 'string', 'max:100'],
            'unidades.*.capacidad' => ['nullable', 'string', 'max:20'],
            'unidades.*.tipo_agente' => ['nullable', 'string', 'max:60'],
            'unidades.*.anio_fabricacion' => ['nullable', 'string', 'max:10'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['motivo.required' => 'Escribe por qué se corrige el certificado.'];
    }
}
