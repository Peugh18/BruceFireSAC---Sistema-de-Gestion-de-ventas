<?php

namespace App\Http\Requests\Clientes;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreClientRequest extends FormRequest
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
            'tipo_documento' => ['required', Rule::in(['dni', 'ruc'])],
            'numero_documento' => [
                'required',
                'string',
                Rule::unique('clients', 'numero_documento')->where('tipo_documento', $this->input('tipo_documento')),
            ],
            'razon_social' => ['required', 'string', 'max:255'],
            'nombre_comercial' => ['nullable', 'string', 'max:255'],
            'telefono' => ['nullable', 'string', 'max:255'],
            'whatsapp' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'direccion_fiscal' => ['nullable', 'string', 'max:255'],
            'departamento' => ['nullable', 'string', 'max:255'],
            'provincia' => ['nullable', 'string', 'max:255'],
            'distrito' => ['nullable', 'string', 'max:255'],
            'ubigeo' => ['nullable', 'string', 'size:6'],
            'estado_contribuyente' => ['nullable', 'string', 'max:255'],
            'condicion_domicilio' => ['nullable', 'string', 'max:255'],
            'consultado_at' => ['nullable', 'date'],
            'activo' => ['sometimes', 'boolean'],
            'observaciones' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $this->validateDocumentLength($validator);
            },
        ];
    }

    protected function validateDocumentLength(Validator $validator): void
    {
        if ($validator->errors()->hasAny(['tipo_documento', 'numero_documento'])) {
            return;
        }

        $expectedLength = $this->input('tipo_documento') === 'dni' ? 8 : 11;

        if (mb_strlen((string) $this->input('numero_documento')) !== $expectedLength) {
            $validator->errors()->add('numero_documento', "El numero de documento debe tener {$expectedLength} caracteres.");
        }
    }
}
