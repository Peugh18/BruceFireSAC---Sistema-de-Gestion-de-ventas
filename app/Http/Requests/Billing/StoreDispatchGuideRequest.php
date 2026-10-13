<?php

namespace App\Http\Requests\Billing;

use App\Support\DatosPeru;
use App\Support\Ruc;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreDispatchGuideRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('guias_remision.create') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'destinatario_num_doc' => preg_replace('/[\s-]/', '', (string) $this->input('destinatario_num_doc')),
            'transportista_ruc' => DatosPeru::digitos($this->input('transportista_ruc')),
        ]);

        // Lo que quedó escrito de la otra modalidad se descarta: no debe bloquear el envío.
        if ($this->input('modalidad') === '02') {
            $this->merge(['transportista_ruc' => null, 'transportista_razon' => null]);
        } elseif ($this->input('modalidad') === '01') {
            $this->merge(['transport_vehicle_id' => null, 'driver_id' => null]);
        }
    }

    /**
     * @return array<string, list<mixed>|ValidationRule|string>
     */
    public function rules(): array
    {
        return [
            'sale_id' => ['nullable', 'integer', 'exists:sales,id'],
            'service_order_id' => ['nullable', 'integer', 'exists:service_orders,id'],
            'inventory_transfer_id' => ['nullable', 'integer', 'exists:inventory_transfers,id'],
            'motivo' => ['required', Rule::in(['01', '04', '13'])],
            'motivo_descripcion' => ['nullable', 'required_if:motivo,13', 'string', 'max:250'],
            'modalidad' => ['required', Rule::in(['01', '02'])],
            'fecha_traslado' => ['required', 'date', 'after_or_equal:today'],
            'destinatario_tipo_doc' => ['required', Rule::in(['1', '6'])],
            'destinatario_num_doc' => ['required', 'string', 'max:15'],
            'destinatario_nombre' => ['required', 'string', 'max:255'],
            'partida_ubigeo' => ['required', 'string', 'size:6', 'exists:ubigeos,codigo'],
            'partida_direccion' => ['required', 'string', 'max:255'],
            'partida_cod_establecimiento' => ['nullable', 'string', 'regex:/^\d{4}$/'],
            'llegada_ubigeo' => ['required', 'string', 'size:6', 'exists:ubigeos,codigo'],
            'llegada_direccion' => ['required', 'string', 'max:255'],
            'llegada_cod_establecimiento' => ['nullable', 'string', 'regex:/^\d{4}$/'],
            'peso_bruto' => ['required', 'numeric', 'gt:0', 'max:9999999'],
            'transport_vehicle_id' => ['nullable', 'required_if:modalidad,02', Rule::exists('transport_vehicles', 'id')->where('activo', true)],
            'driver_id' => ['nullable', 'required_if:modalidad,02', Rule::exists('drivers', 'id')->where('activo', true)],
            'transportista_ruc' => ['nullable', 'required_if:modalidad,01', 'digits:11'],
            'transportista_razon' => ['nullable', 'required_if:modalidad,01', 'string', 'max:255'],
            'doc_relacionado_tipo' => ['nullable', Rule::in(['01', '03'])],
            'doc_relacionado_numero' => ['nullable', 'string', 'max:20'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['nullable', 'integer', 'exists:products,id'],
            'items.*.codigo' => ['nullable', 'string', 'max:30'],
            'items.*.descripcion' => ['required', 'string', 'max:255'],
            'items.*.unidad' => ['required', 'string', 'min:2', 'max:3'],
            'items.*.cantidad' => ['required', 'numeric', 'gt:0'],
            'items.*.peso_kg' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $errores = $validator->errors();
                $numero = (string) $this->input('destinatario_num_doc');

                if (! $errores->has('destinatario_num_doc') && $this->input('destinatario_tipo_doc') === '1' && preg_match('/^\d{8}$/', $numero) !== 1) {
                    $errores->add('destinatario_num_doc', 'El DNI tiene 8 números.');
                }

                if (! $errores->has('destinatario_num_doc') && $this->input('destinatario_tipo_doc') === '6' && ! Ruc::esValido($numero)) {
                    $errores->add('destinatario_num_doc', 'El RUC del destinatario no es válido: revisa los dígitos.');
                }

                $transportista = (string) $this->input('transportista_ruc');

                if (! $errores->has('transportista_ruc') && $transportista !== '' && ! Ruc::esValido($transportista)) {
                    $errores->add('transportista_ruc', 'El RUC del transportista no es válido: revisa los dígitos.');
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'motivo_descripcion.required_if' => 'Describe el motivo del traslado.',
            'transport_vehicle_id.required_if' => 'El transporte privado necesita el vehículo.',
            'driver_id.required_if' => 'El transporte privado necesita el conductor.',
            'transportista_ruc.required_if' => 'El transporte público necesita el RUC del transportista.',
            'transportista_razon.required_if' => 'El transporte público necesita la razón social del transportista.',
            'peso_bruto.gt' => 'El peso bruto debe ser mayor que cero.',
            'fecha_traslado.after_or_equal' => 'La fecha de inicio del traslado no puede ser anterior a hoy.',
            'items.required' => 'Agrega al menos un bien a trasladar.',
        ];
    }
}
