<?php

namespace App\Http\Requests\TecnicoCampo;

use App\Models\Sede;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IniciarRecojoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('TecnicoCampo') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $sedeIds = $this->user()?->sede_id
            ? Sede::idsComercialesAtendidasPor((int) $this->user()->sede_id)
            : [];

        if (count($sedeIds) === 1) {
            $this->merge(['sede_id' => $sedeIds[0]]);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            // El cliente nuevo se registra antes, con el alta completa de Clientes.
            'client_id' => ['required', 'integer', 'exists:clients,id'],
            'sede_id' => ['required', 'integer', Rule::in($this->user()?->sede_id ? Sede::idsComercialesAtendidasPor((int) $this->user()->sede_id) : [])],
            'referencia' => ['required', 'string', 'max:150'],
            'cantidad' => ['nullable', 'integer', 'min:1', 'max:100'],
            'service_id' => ['nullable', 'integer', Rule::exists('services', 'id')->where('activo', true)],
            'prioridad' => ['nullable', Rule::in(['normal', 'alta', 'urgente'])],
            'observaciones' => ['nullable', 'string', 'max:1000'],
            'precio_acordado' => ['nullable', 'numeric', 'min:0.01', 'max:999999.99', 'decimal:0,2'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['client_id.required' => 'Elige al cliente o regístralo con «Agregar cliente».'];
    }
}
