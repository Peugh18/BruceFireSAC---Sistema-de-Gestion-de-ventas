<?php

namespace App\Http\Requests\Gerente;

use App\Enums\EquipmentType;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Support\UnidadMedidaSunat;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
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
        $producto = $this->route('producto');

        return [
            'codigo' => [
                'required',
                'string',
                'max:50',
                Rule::unique('products', 'codigo')->ignore($producto),
            ],
            // El del fabricante (EAN/UPC): solo letras y números, y no se
            // repite entre productos.
            'codigo_barras' => ['nullable', 'string', 'max:64', 'regex:/^[A-Za-z0-9-]+$/', Rule::unique('products', 'codigo_barras')->ignore($producto)],
            'categoria' => ['nullable', 'string', 'max:40', ProductCategory::regla($producto instanceof Product ? $producto->categoria : null)],
            // C1: el agente y la capacidad pasan a la unidad y al equipo vendido.
            'agente' => ['nullable', Rule::enum(EquipmentType::class)],
            'capacidad' => ['nullable', 'string', 'max:20'],
            // Fase H: el peso unitario alimenta el peso bruto de la guía de remisión.
            'peso_kg' => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'nombre' => ['required', 'string', 'max:255'],
            'descripcion' => ['nullable', 'string', 'max:1000'],
            'unidad_medida' => ['required', Rule::in(UnidadMedidaSunat::codigos())],
            'precio_venta' => ['required', 'numeric', 'min:0'],
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
