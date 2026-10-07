<?php

namespace App\Http\Requests\Gerente;

use App\Models\ProductCategory;
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
            'codigo' => ['required', 'string', 'max:50', 'unique:services,codigo'],
            'nombre' => ['required', 'string', 'max:255'],
            'descripcion' => ['nullable', 'string', 'max:1000'],
            'unidad_medida' => ['required', Rule::in(UnidadMedidaSunat::codigos())],
            'categoria' => ['nullable', 'string', 'max:40', ProductCategory::regla()],
            'precio_venta' => ['required', 'numeric', 'min:0'],
            'aplica_igv' => ['boolean'],
            'tipo_afectacion_igv' => ['sometimes', 'required', Rule::in(['10', '20', '30'])],
            // A4: el certificado que sale al terminar el servicio.
            'certificate_type_id' => ['nullable', 'integer', 'exists:certificate_types,id'],
            'activo' => ['boolean'],
        ];
    }
}
