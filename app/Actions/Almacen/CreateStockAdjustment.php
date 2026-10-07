<?php

namespace App\Actions\Almacen;

use App\Models\InventoryMovement;
use App\Models\InventoryUnit;
use App\Models\Product;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Inventory\StockPorLote;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateStockAdjustment
{
    /**
     * Aplica un ajuste de inventario directo generando un nuevo InventoryMovement tipo 'ajuste' (§84.10).
     *
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data, ?User $user = null): InventoryMovement
    {
        return DB::transaction(function () use ($data, $user) {
            $productId = (int) $data['product_id'];
            $sedeId = (int) $data['sede_id'];
            $tipoAjuste = (string) $data['tipo_ajuste'];
            $rawCantidad = (int) $data['cantidad'];
            $signedCantidad = $tipoAjuste === 'decremento' ? -$rawCantidad : $rawCantidad;
            $unitId = ! empty($data['inventory_unit_id']) ? (int) $data['inventory_unit_id'] : null;

            $motivo = trim((string) $data['motivo']);
            $observacion = ! empty($data['observacion']) ? trim((string) $data['observacion']) : null;
            $textoKardex = $observacion ? "{$motivo} - {$observacion}" : $motivo;

            $datos = ['tipo' => 'ajuste', 'user_id' => $user?->id ?? auth()->id(), 'observacion' => $textoKardex];
            $stock = app(StockPorLote::class);

            if ($unitId) {
                // Con la unidad bloqueada: dos ajustes a la vez no la dan de
                // baja (ni la reingresan) dos veces.
                $estadoActual = InventoryUnit::query()->lockForUpdate()->findOrFail($unitId)->estado;
                if (($tipoAjuste === 'decremento' && $estadoActual !== 'disponible')
                    || ($tipoAjuste === 'incremento' && $estadoActual !== 'baja')) {
                    throw ValidationException::withMessages([
                        'inventory_unit_id' => $tipoAjuste === 'decremento'
                            ? "Esa unidad ya no está disponible en el almacén (estado: {$estadoActual})."
                            : "Solo se puede reingresar una unidad dada de baja (esta está: {$estadoActual}).",
                    ]);
                }

                $movement = InventoryMovement::create([
                    'inventory_unit_id' => $unitId,
                    'product_id' => $productId,
                    'sede_id' => $sedeId,
                    'tipo' => 'ajuste',
                    'cantidad' => $signedCantidad,
                    'user_id' => $datos['user_id'],
                    'observacion' => $textoKardex,
                ]);
            } elseif ($tipoAjuste === 'decremento') {
                // Con bloqueo, sin dejar el stock en negativo; con lote, del
                // lote elegido o de lo que vence primero.
                $loteId = ! empty($data['product_lot_id']) ? (int) $data['product_lot_id'] : null;
                $movement = $stock->sacar(Product::findOrFail($productId), $sedeId, $rawCantidad, $datos, loteId: $loteId, verbo: 'dar de baja')[0];
            } else {
                $movement = $stock->ingresar(Product::findOrFail($productId), $sedeId, $rawCantidad, $datos, $data['lote'] ?? null, $data['fecha_vencimiento'] ?? null);
            }

            // Si se especificó una unidad serializada puntual, actualizamos su estado
            if ($unitId) {
                $unit = InventoryUnit::find($unitId);
                if ($unit) {
                    if ($tipoAjuste === 'decremento') {
                        $unit->update(['estado' => 'baja']);
                    } elseif ($tipoAjuste === 'incremento') {
                        $unit->update(['estado' => 'disponible']);
                    }
                }
            }

            AuditLogger::log(
                action: 'stock.ajuste',
                entity: $movement,
                newValues: [
                    'product_id' => $productId,
                    'sede_id' => $sedeId,
                    'tipo_ajuste' => $tipoAjuste,
                    'cantidad' => $signedCantidad,
                    'motivo' => $textoKardex,
                ],
                userId: $user?->id ?? auth()->id()
            );

            return $movement;
        });
    }
}
