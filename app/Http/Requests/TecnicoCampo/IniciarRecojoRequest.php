<?php

namespace App\Http\Requests\TecnicoCampo;

use App\Models\Sede;
use App\Rules\TelefonoPeru;
use App\Support\DatosPeru;
use App\Support\Ruc;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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

        if (is_array($cliente = $this->input('cliente'))) {
            $documento = DatosPeru::digitos($cliente['numero_documento'] ?? null);
            $this->merge(['cliente' => [
                ...$cliente,
                'telefono' => DatosPeru::telefono($cliente['telefono'] ?? null),
                'tipo_documento' => $cliente['tipo_documento'] ?? match (strlen((string) $documento)) {
                    8 => 'dni',
                    11 => 'ruc',
                    default => null,
                },
                'numero_documento' => $documento,
            ]]);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'client_id' => ['nullable', 'integer', 'exists:clients,id'],
            'cliente' => ['required_without:client_id', 'nullable', 'array:razon_social,telefono,tipo_documento,numero_documento'],
            'cliente.razon_social' => ['required_without:client_id', 'nullable', 'string', 'max:255'],
            'cliente.telefono' => ['required_without:client_id', 'nullable', new TelefonoPeru],
            'cliente.tipo_documento' => ['nullable', Rule::in(['dni', 'ruc'])],
            'cliente.numero_documento' => ['nullable', 'string', 'regex:/^(\d{8}|\d{11})$/'],
            'sede_id' => ['required', 'integer', Rule::in($this->user()?->sede_id ? Sede::idsComercialesAtendidasPor((int) $this->user()->sede_id) : [])],
            'referencia' => ['required', 'string', 'max:150'],
            'cantidad' => ['required', 'integer', 'min:1', 'max:100'],
            'service_id' => ['nullable', 'integer', Rule::exists('services', 'id')->where('activo', true)],
            'prioridad' => ['nullable', Rule::in(['normal', 'alta', 'urgente'])],
            'observaciones' => ['nullable', 'string', 'max:1000'],
            'precio_acordado' => ['nullable', 'numeric', 'min:0.01', 'max:999999.99', 'decimal:0,2'],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $documento = $this->input('cliente.numero_documento');
            if (! filled($documento) || $validator->errors()->has('cliente.numero_documento')) {
                return;
            }
            $tipo = $this->input('cliente.tipo_documento');
            if (($tipo === 'dni' && strlen($documento) !== 8) || ($tipo === 'ruc' && strlen($documento) !== 11)) {
                $validator->errors()->add('cliente.numero_documento', 'El documento no coincide con el tipo elegido.');
            }
            if (strlen($documento) === 11 && ! Ruc::esValido($documento)) {
                $validator->errors()->add('cliente.numero_documento', 'Ese RUC no es válido: revisa los números.');
            }
        }];
    }
}
