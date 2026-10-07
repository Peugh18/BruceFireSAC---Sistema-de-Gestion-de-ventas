<?php

namespace App\Http\Requests\Sales;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class EmitirCertificadosRequest extends FormRequest
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
            'grupos' => ['required', 'array', 'min:1', 'max:30'],
            'grupos.*.referencia' => ['nullable', 'string', 'max:150'],
            'grupos.*.direccion' => ['nullable', 'string', 'max:255'],
            'grupos.*.tipos' => ['required', 'array', 'min:1'],
            'grupos.*.tipos.*' => [Rule::in(['operatividad_garantia', 'prueba_hidrostatica', 'capacitacion'])],
            'grupos.*.unidades' => ['present', 'array'],
            'grupos.*.unidades.*.equipment_id' => ['required', 'integer', 'distinct', 'exists:equipment,id'],
            'grupos.*.unidades.*.numero_cliente' => ['nullable', 'string', 'max:40'],
            // Prueba hidrostática: lo que registró el técnico (C2).
            'grupos.*.unidades.*.fecha_ultima_ph' => ['nullable', 'date', 'before_or_equal:today'],
            'grupos.*.unidades.*.presion_ph' => ['nullable', 'string', 'max:20'],
            'grupos.*.unidades.*.tiempo_ph' => ['nullable', 'string', 'max:20'],
            'grupos.*.unidades.*.resultado_ph' => ['nullable', Rule::in(['aprobado', 'desaprobado'])],
            'grupos.*.capacitacion.curso' => ['nullable', 'string', 'max:120'],
            'grupos.*.capacitacion.horas' => ['nullable', 'integer', 'min:1', 'max:40'],
            'grupos.*.capacitacion.instructor' => ['nullable', 'string', 'max:120'],
            'grupos.*.capacitacion.modo' => ['nullable', Rule::in(['normal', 'con_fotos', 'por_trabajador'])],
            'grupos.*.capacitacion.fotos' => ['nullable', 'array', 'max:3'],
            'grupos.*.capacitacion.fotos.*' => ['image', 'mimes:jpg,jpeg,png', 'max:5120'],
            'grupos.*.capacitacion.participantes' => ['nullable', 'array', 'max:100'],
            'grupos.*.capacitacion.participantes.*.id' => ['nullable', 'integer'],
            'grupos.*.capacitacion.participantes.*.nombres' => ['required', 'string', 'max:150'],
            'grupos.*.capacitacion.participantes.*.dni' => ['nullable', 'string', 'max:20'],
            'grupos.*.capacitacion.participantes.*.cargo' => ['nullable', 'string', 'max:100'],
            'motivo' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'grupos.*.tipos.required' => 'Cada grupo necesita al menos un tipo de certificado.',
            'grupos.*.unidades.*.equipment_id.distinct' => 'Un extintor no puede estar en dos grupos.',
        ];
    }

    /** @return array<int, callable> */
    public function after(): array
    {
        return [function (Validator $validator) {
            foreach ($this->input('grupos', []) as $index => $grupo) {
                $capacitacion = $grupo['capacitacion'] ?? null;

                if (($capacitacion['modo'] ?? null) === 'por_trabajador' && empty($capacitacion['participantes'])) {
                    $validator->errors()->add("grupos.{$index}.capacitacion.participantes", 'Agrega al menos un trabajador.');
                }
            }
        }];
    }
}
