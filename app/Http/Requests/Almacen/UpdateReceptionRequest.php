<?php

namespace App\Http\Requests\Almacen;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateReceptionRequest extends FormRequest
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
            'proveedor' => ['required', 'string', 'max:150'],
            'documento_referencia' => ['nullable', 'string', 'max:50'],
            'fecha' => ['required', 'date', 'before_or_equal:today'],
            'observacion' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['required', 'integer', 'exists:reception_items,id'],
            'items.*.cantidad' => ['required', 'integer', 'min:1'],
            'items.*.cantidad_conforme' => ['required', 'integer', 'min:0'],
            'items.*.observacion_item' => ['nullable', 'string'],
            'items.*.unidades_nuevas' => ['nullable', 'array'],
            'items.*.unidades_nuevas.*.capacidad' => ['required_with:items.*.unidades_nuevas', 'string', 'max:50'],
            'items.*.unidades_nuevas.*.serie_fabricante' => ['nullable', 'string', 'max:100'],
            'items.*.unidades_nuevas.*.marca' => ['required_with:items.*.unidades_nuevas', 'string', 'max:100'],
            'items.*.unidades_nuevas.*.anio_fabricacion' => [
                'required_with:items.*.unidades_nuevas',
                'integer',
                'min:1990',
                'max:'.(int) date('Y'),
            ],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $items = $this->input('items', []);
                if (! is_array($items)) {
                    return;
                }

                foreach ($items as $index => $item) {
                    $cantidad = (int) ($item['cantidad'] ?? 0);
                    $conforme = (int) ($item['cantidad_conforme'] ?? 0);
                    $obs = trim((string) ($item['observacion_item'] ?? ''));

                    if ($conforme > $cantidad) {
                        $validator->errors()->add(
                            "items.{$index}.cantidad_conforme",
                            'La cantidad conforme no puede superar la cantidad recibida.'
                        );
                    }

                    if ($conforme < $cantidad && $obs === '') {
                        $validator->errors()->add(
                            "items.{$index}.observacion_item",
                            'Debe indicar el motivo u observación de la mercadería no conforme para reclamo al proveedor.'
                        );
                    }
                }
            },
        ];
    }
}
