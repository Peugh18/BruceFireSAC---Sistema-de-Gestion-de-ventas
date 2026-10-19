<?php

namespace App\Http\Requests\Gerente;

use App\Enums\EquipmentType;
use App\Models\ProductCategory;
use App\Support\CatalogosSunat;
use App\Support\UnidadMedidaSunat;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductRequest extends FormRequest
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
            // El del fabricante (EAN/UPC): solo letras y números, y no se
            // repite entre productos.
            'codigo_barras' => ['nullable', 'string', 'max:64', 'regex:/^[A-Za-z0-9-]+$/', 'unique:products,codigo_barras'],
            // Catálogo 25 (UNSPSC), opcional: va al XML de la factura.
            'codigo_sunat' => CatalogosSunat::reglaCodigoProducto(),
            'categoria' => ['nullable', 'string', 'max:40', ProductCategory::regla()],
            // C1: el agente y la capacidad pasan a la unidad y al equipo vendido.
            // De la lista administrable de agentes (la misma que usan los técnicos).
            'agente' => ['nullable', 'string', Rule::in(EquipmentType::etiquetas())],
            'capacidad' => ['nullable', 'string', 'max:20'],
            // Fase H: el peso unitario alimenta el peso bruto de la guía de remisión.
            'peso_kg' => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'nombre' => ['required', 'string', 'max:255'],
            'descripcion' => ['nullable', 'string', 'max:1000'],
            'unidad_medida' => ['required', Rule::in(UnidadMedidaSunat::codigos())],
            'precio_venta' => ['required', 'numeric', 'min:0', 'decimal:0,2', 'max:9999999.99'],
            'aplica_igv' => ['boolean'],
            'tipo_afectacion_igv' => ['sometimes', 'required', Rule::in(['10', '20', '30'])],
            'serializado' => ['boolean'],
            // EPP y consumibles: stock por lote con vencimiento (no aplica a
            // productos con serie), y compra por caja con su equivalencia.
            'controla_lote' => ['boolean', function (string $attribute, mixed $value, \Closure $fail): void {
                if ($this->boolean('controla_lote') && $this->boolean('serializado')) {
                    $fail('Un producto con número de serie no se controla por lote.');
                }
            }],
            'unidad_compra' => ['nullable', 'string', 'max:10'],
            'factor_compra' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'stock_minimo' => ['nullable', 'integer', 'min:0'],
            'activo' => ['boolean'],
        ];
    }

    /**
     * Sin caja de compra, se compra por la misma unidad (factor 1).
     */
    protected function prepareForValidation(): void
    {
        $unidadCompra = mb_strtoupper(trim((string) $this->input('unidad_compra', '')));

        // Lo mínimo para el gerente: si no dice otra cosa, se vende por unidad y es gravado (IGV 18 %).
        $this->mergeIfMissing(['unidad_medida' => 'NIU', 'tipo_afectacion_igv' => '10']);
        $this->merge([
            'unidad_compra' => $unidadCompra !== '' ? $unidadCompra : null,
            'factor_compra' => $unidadCompra !== '' && $this->filled('factor_compra') ? $this->input('factor_compra') : 1,
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'codigo_barras.unique' => 'Ese código de barras ya está registrado en otro producto.',
            'codigo_barras.regex' => 'El código de barras solo lleva letras, números y guiones.',
        ];
    }
}
