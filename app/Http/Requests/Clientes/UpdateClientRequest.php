<?php

namespace App\Http\Requests\Clientes;

use App\Models\Client;
use App\Support\Ruc;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateClientRequest extends FormRequest
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
        $client = $this->route('client');

        return [
            'tipo_documento' => ['required', Rule::in(['dni', 'ruc'])],
            'numero_documento' => [
                'required',
                'string',
                Rule::unique('clients', 'numero_documento')
                    ->where('tipo_documento', $this->input('tipo_documento'))
                    ->ignore($client instanceof Client ? $client->id : null),
            ],
            'razon_social' => ['required', 'string', 'max:255'],
            'nombre_comercial' => ['nullable', 'string', 'max:255'],
            'telefono' => ['nullable', 'string', 'max:255'],
            'whatsapp' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'direccion_fiscal' => ['nullable', 'string', 'max:255'],
            'ubigeo' => ['nullable', 'string', 'size:6', 'exists:ubigeos,codigo'],
            // El estado y la condición ante SUNAT no se escriben a mano: los
            // pone el servidor con la consulta (si no, se saltaría la regla
            // de facturar solo a RUC Activo y Habido).
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
                $this->validarRuc($validator);
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

    /**
     * Un RUC con el dígito verificador mal es un error de tipeo.
     */
    protected function validarRuc(Validator $validator): void
    {
        if ($validator->errors()->has('numero_documento') || $this->input('tipo_documento') !== 'ruc') {
            return;
        }

        $client = $this->route('client');
        $cambiaDocumento = $client instanceof Client
            && ($client->numero_documento !== (string) $this->input('numero_documento') || $client->tipo_documento !== $this->input('tipo_documento'));

        if ($client instanceof Client && $cambiaDocumento && ($client->esClientesVarios() || $client->sales()->where('estado', 'confirmada')->exists())) {
            $validator->errors()->add('numero_documento', 'Este cliente ya tiene ventas emitidas: no se cambia su documento. Registra un cliente nuevo con el documento correcto.');

            return;
        }

        if (! $cambiaDocumento) {
            return;
        }

        if (! Ruc::esValido((string) $this->input('numero_documento'))) {
            $validator->errors()->add('numero_documento', 'El RUC no es válido: revisa los dígitos (debe empezar con 10 o 20 y su último dígito no coincide).');
        }
    }
}
