<?php

namespace App\Http\Requests\Almacen;

use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreReceptionRequest extends FormRequest
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
            'sede_almacen_id' => [
                'required',
                'integer',
                Rule::exists('sedes', 'id')->where(function ($query) {
                    $query->whereIn('tipo', ['almacen', 'mixta'])->where('activo', true);
                }),
            ],
            'observacion' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.cantidad' => ['required', 'integer', 'min:1'],
            'items.*.cantidad_conforme' => ['required', 'integer', 'min:0'],
            'items.*.costo_unitario' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'items.*.observacion_item' => ['nullable', 'string'],
            'items.*.lote' => ['nullable', 'string', 'max:50'],
            'items.*.fecha_vencimiento' => ['nullable', 'date'],
            'items.*.unidades' => ['nullable', 'array'],
            'items.*.unidades.*.capacidad' => ['required_with:items.*.unidades', 'string', 'max:50'],
            'items.*.unidades.*.serie_fabricante' => ['nullable', 'string', 'max:100'],
            'items.*.unidades.*.marca' => ['required_with:items.*.unidades', 'string', 'max:100'],
            'items.*.unidades.*.anio_fabricacion' => [
                'required_with:items.*.unidades',
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
                // Cada almacenero recibe mercadería en su almacén.
                $almacenPropio = $this->user()?->almacenRestringidoId();
                if ($almacenPropio !== null && (int) $this->input('sede_almacen_id') !== $almacenPropio) {
                    $validator->errors()->add('sede_almacen_id', 'Solo puedes registrar recepciones en tu almacén.');
                }
            },
            function (Validator $validator): void {
                $items = $this->input('items', []);
                if (! is_array($items)) {
                    return;
                }

                $productIds = collect($items)->pluck('product_id')->filter()->all();
                $products = Product::whereIn('id', $productIds)->get()->keyBy('id');

                foreach ($items as $index => $item) {
                    $cantidad = (int) ($item['cantidad'] ?? 0);
                    $conforme = (int) ($item['cantidad_conforme'] ?? 0);
                    $obs = trim((string) ($item['observacion_item'] ?? ''));

                    // Validación lte:cantidad
                    if ($conforme > $cantidad) {
                        $validator->errors()->add(
                            "items.{$index}.cantidad_conforme",
                            'La cantidad conforme no puede superar la cantidad recibida.'
                        );
                    }

                    // Regla obligatoria: observacion requerida si cantidad_conforme < cantidad
                    if ($conforme < $cantidad && $obs === '') {
                        $validator->errors()->add(
                            "items.{$index}.observacion_item",
                            'Debe indicar el motivo u observación de la mercadería no conforme para reclamo al proveedor.'
                        );
                    }

                    // Validación de unidades serializadas
                    $productId = $item['product_id'] ?? null;
                    $product = $products->get($productId);

                    // EPP y consumibles con lote: lote y vencimiento, y nada
                    // vencido entra al almacén.
                    if ($product && $product->controla_lote && $conforme > 0) {
                        if (trim((string) ($item['lote'] ?? '')) === '') {
                            $validator->errors()->add("items.{$index}.lote", "{$product->nombre} lleva lote: escribe el número de lote.");
                        }
                        $vence = $item['fecha_vencimiento'] ?? null;
                        if (! $vence) {
                            $validator->errors()->add("items.{$index}.fecha_vencimiento", "Indica el vencimiento del lote de {$product->nombre}.");
                        } elseif (strtotime((string) $vence) !== false && strtotime((string) $vence) < strtotime(today()->toDateString())) {
                            $validator->errors()->add("items.{$index}.fecha_vencimiento", "El lote de {$product->nombre} ya está vencido: no se recibe.");
                        }
                    }

                    if ($product && $product->serializado) {
                        $unidades = $item['unidades'] ?? [];
                        if (! is_array($unidades) || count($unidades) !== $conforme) {
                            $validator->errors()->add(
                                "items.{$index}.unidades",
                                "Debe registrar los datos de las {$conforme} unidades serializadas conformes."
                            );
                        }
                    }
                }
            },
        ];
    }
}
