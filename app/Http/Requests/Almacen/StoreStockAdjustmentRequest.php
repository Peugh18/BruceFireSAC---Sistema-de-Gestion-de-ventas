<?php

namespace App\Http\Requests\Almacen;

use App\Models\InventoryMovement;
use App\Models\InventoryUnit;
use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreStockAdjustmentRequest extends FormRequest
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
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'inventory_unit_id' => ['nullable', 'integer', 'exists:inventory_units,id'],
            'sede_id' => [
                'required',
                'integer',
                Rule::exists('sedes', 'id')->where(function ($query) {
                    $query->whereIn('tipo', ['almacen', 'mixta'])->where('activo', true);
                }),
            ],
            'tipo_ajuste' => ['required', 'string', 'in:incremento,decremento'],
            'cantidad' => ['required', 'integer', 'min:1'],
            'motivo' => ['required', 'string', 'min:10', 'max:255'],
            'observacion' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * Reglas de validación contextuales (§84.10):
     * - Si se especifica una unidad serializada, la cantidad debe ser exactamente 1.
     * - La unidad serializada debe pertenecer al producto y a la sede especificada.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $unitId = $this->input('inventory_unit_id');
                $productId = (int) $this->input('product_id');
                $sedeId = (int) $this->input('sede_id');
                $cantidad = (int) $this->input('cantidad');
                $tipoAjuste = $this->input('tipo_ajuste');

                // Cada almacenero ajusta solo el stock de su almacén.
                $almacenPropio = $this->user()?->almacenRestringidoId();
                if ($almacenPropio !== null && $sedeId !== $almacenPropio) {
                    $validator->errors()->add('sede_id', 'Solo puedes ajustar el stock de tu almacén.');

                    return;
                }

                if ($unitId) {
                    if ($cantidad !== 1) {
                        $validator->errors()->add(
                            'cantidad',
                            'Cuando el ajuste es sobre una unidad serializada específica, la cantidad debe ser 1.'
                        );
                    }

                    $unit = InventoryUnit::query()->find((int) $unitId);
                    if ($unit) {
                        if ($unit->product_id !== $productId) {
                            $validator->errors()->add(
                                'inventory_unit_id',
                                'La unidad seleccionada no corresponde al producto elegido.'
                            );
                        }
                        if ($unit->sede_almacen_id !== $sedeId) {
                            $validator->errors()->add(
                                'inventory_unit_id',
                                'La unidad seleccionada no se encuentra ubicada en la sede indicada.'
                            );
                        }
                        // Solo se da de baja lo que está en el almacén y solo
                        // vuelve lo que se dio de baja: un extintor vendido
                        // no se "revive" con un ajuste.
                        if ($tipoAjuste === 'decremento' && $unit->estado !== 'disponible') {
                            $validator->errors()->add('inventory_unit_id', "Esa unidad no está disponible en el almacén (estado: {$unit->estado}).");
                        }
                        if ($tipoAjuste === 'incremento' && $unit->estado !== 'baja') {
                            $validator->errors()->add('inventory_unit_id', "Solo se puede reingresar una unidad dada de baja (esta está: {$unit->estado}).");
                        }
                    }
                } else {
                    $product = Product::find($productId);
                    if ($product && $product->serializado && $tipoAjuste === 'decremento') {
                        $validator->errors()->add(
                            'inventory_unit_id',
                            'Para dar de baja stock de un producto serializado, debe seleccionar la unidad física específica.'
                        );
                    }
                    // Un extintor nuevo entra con su serie por Recepciones:
                    // sumar sin unidad descuadra el Kardex con el stock.
                    if ($product && $product->serializado && $tipoAjuste === 'incremento') {
                        $validator->errors()->add(
                            'inventory_unit_id',
                            'Los productos con serie entran por Recepciones (cada unidad con su serie), no por ajuste.'
                        );
                    }
                    if ($product && ! $product->serializado && $tipoAjuste === 'decremento') {
                        $saldo = InventoryMovement::saldo($product->id, $sedeId);
                        if ($saldo < $cantidad) {
                            $validator->errors()->add('cantidad', "Solo hay {$saldo} en este almacén: no se pueden dar de baja {$cantidad}.");
                        }
                    }
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
            'motivo.required' => 'El motivo del ajuste es estrictamente obligatorio por regla de auditoría (§84.10).',
            'motivo.min' => 'El motivo debe tener al menos 10 caracteres explicativos para auditoría.',
        ];
    }
}
