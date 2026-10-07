<?php

namespace App\Http\Requests\Billing;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDispatchGuideRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('guias_remision.create') ?? false;
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
