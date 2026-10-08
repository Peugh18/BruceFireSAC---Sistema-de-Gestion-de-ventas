<?php

namespace App\Http\Requests\Almacen;

use App\Models\InventoryUnit;
use App\Models\Product;
use App\Services\Inventory\ReglasDeAjusteDeStock;
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
            // Productos con lote: el ingreso dice su lote y vencimiento; la
            // baja puede ser de un lote puntual (por ejemplo, uno vencido).
            'product_lot_id' => ['nullable', 'integer', 'exists:product_lots,id'],
            'lote' => ['nullable', 'string', 'max:50'],
            'fecha_vencimiento' => ['nullable', 'date'],
        ];
    }

    /**
     * Reglas de validación contextuales (§84.10), definidas en
     * ReglasDeAjusteDeStock para compartirlas con la Action que aplica el
     * ajuste. Todas siguen activas aquí: no se debilita ninguna.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $unitId = $this->input('inventory_unit_id') ? (int) $this->input('inventory_unit_id') : null;
                $productId = (int) $this->input('product_id');
                $sedeId = (int) $this->input('sede_id');
                $cantidad = (int) $this->input('cantidad');
                $tipoAjuste = (string) $this->input('tipo_ajuste');

                // Cada almacenero ajusta solo el stock de su almacén.
                $almacenPropio = $this->user()?->almacenRestringidoId();
                if ($almacenPropio !== null && $sedeId !== $almacenPropio) {
                    $validator->errors()->add('sede_id', 'Solo puedes ajustar el stock de tu almacén.');

                    return;
                }

                if ($unitId) {
                    $unit = InventoryUnit::query()->find($unitId);

                    $error = ReglasDeAjusteDeStock::errorDeCantidadParaUnidad($unitId, $cantidad);
                    if ($error !== null) {
                        $validator->errors()->add('cantidad', $error);
                    }

                    $error = ReglasDeAjusteDeStock::errorDeUnidadDeOtroProducto($unit, $productId);
                    if ($error !== null) {
                        $validator->errors()->add('inventory_unit_id', $error);
                    }

                    if ($unit) {
                        $error = ReglasDeAjusteDeStock::errorDeUnidadDeOtraSede($unit, $sedeId);
                        if ($error !== null) {
                            $validator->errors()->add('inventory_unit_id', $error);
                        }

                        // Solo se da de baja lo que está en el almacén y solo
                        // vuelve lo que se dio de baja: un extintor vendido
                        // no se "revive" con un ajuste.
                        $error = ReglasDeAjusteDeStock::errorDeEstadoDeLaUnidad($tipoAjuste, (string) $unit->estado);
                        if ($error !== null) {
                            $validator->errors()->add('inventory_unit_id', $error);
                        }
                    }
                } else {
                    $product = Product::find($productId);

                    $error = ReglasDeAjusteDeStock::errorDeProductoSerializadoSinUnidad($product, $tipoAjuste);
                    if ($error !== null) {
                        $validator->errors()->add('inventory_unit_id', $error);
                    }

                    // Un extintor nuevo entra con su serie por Recepciones:
                    // sumar sin unidad descuadra el Kardex con el stock.
                    $error = ReglasDeAjusteDeStock::errorDeLoteFaltante($product, $tipoAjuste, (string) $this->input('lote'));
                    if ($error !== null) {
                        $validator->errors()->add('lote', $error);
                    }

                    $loteId = $this->input('product_lot_id');
                    $error = ReglasDeAjusteDeStock::errorDeLoteAjeno($loteId ? (int) $loteId : null, $productId, $sedeId);
                    if ($error !== null) {
                        $validator->errors()->add('product_lot_id', $error);
                    }

                    $error = ReglasDeAjusteDeStock::errorDeSaldoInsuficiente($product, $tipoAjuste, $cantidad, $sedeId);
                    if ($error !== null) {
                        $validator->errors()->add('cantidad', $error);
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
