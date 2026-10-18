<?php

namespace App\Http\Requests\Gerente;

use App\Enums\EquipmentType;
use App\Models\ProductCategory;
use App\Support\CatalogosSunat;
use App\Support\UnidadMedidaSunat;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('Gerente') ?? false;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            // El tipo trae la detracción, el certificado y el código SUNAT.
            'tipo_de_servicio_id' => ['required', 'integer', Rule::exists('tipos_de_servicio', 'id')->where('activo', true)],
            'nombre' => ['required', 'string', 'max:255'],
            'descripcion' => ['nullable', 'string', 'max:1000'],
            'unidad_medida' => ['required', Rule::in(UnidadMedidaSunat::codigos())],
            'categoria' => ['nullable', 'string', 'max:40', ProductCategory::regla()],
            // X9: "Ofrecer recarga" elige el servicio por agente y capacidad del extintor.
            // De la lista administrable de agentes (la misma que usan los técnicos).
            'agente' => ['nullable', 'string', Rule::in(EquipmentType::etiquetas())],
            'capacidad' => ['nullable', 'string', 'max:20'],
            'precio_venta' => ['required', 'numeric', 'min:0', 'decimal:0,2', 'max:9999999.99'],
            'aplica_igv' => ['boolean'],
            'estado_detraccion' => ['nullable', Rule::in(['no_sujeto', 'pendiente', 'sujeto'])],
            'codigo_detraccion' => ['nullable', 'required_if:estado_detraccion,sujeto', Rule::in(CatalogosSunat::codigosDeDetraccion())],
            'tipo_afectacion_igv' => ['sometimes', 'required', Rule::in(['10', '20', '30'])],
            // A4: el certificado que sale al terminar el servicio.
            'certificate_type_id' => ['nullable', 'integer', 'exists:certificate_types,id'],
            'activo' => ['boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['tipo_de_servicio_id.required' => 'Elige el tipo de servicio.'];
    }

    /** Servicio: unidad de servicio (ZZ) y gravado con IGV, salvo que se indique otra cosa. */
    protected function prepareForValidation(): void
    {
        $this->mergeIfMissing(['unidad_medida' => 'ZZ', 'tipo_afectacion_igv' => '10']);
    }
}
