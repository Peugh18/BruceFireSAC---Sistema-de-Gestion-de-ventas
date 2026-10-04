<?php

namespace App\Http\Requests\Gerente;

use App\Models\Product;
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
            'categoria' => ['nullable', Rule::in(array_keys(Product::CATEGORIAS))],
            'nombre' => ['required', 'string', 'max:255'],
            'descripcion' => ['nullable', 'string', 'max:1000'],
            'unidad_medida' => ['required', 'string', 'max:10'],
            'precio_venta' => ['required', 'numeric', 'min:0'],
            'aplica_igv' => ['boolean'],
            'serializado' => ['boolean'],
            'stock_minimo' => ['nullable', 'integer', 'min:0'],
            'activo' => ['boolean'],
        ];
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
