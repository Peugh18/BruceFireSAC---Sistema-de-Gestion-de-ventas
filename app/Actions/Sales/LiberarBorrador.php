<?php

namespace App\Actions\Sales;

use App\Models\Equipment;
use App\Models\InventoryMovement;
use App\Models\InventoryUnit;
use App\Models\Sale;
use Illuminate\Validation\ValidationException;

/**
 * Deshace lo que reservó una venta en borrador para volver a llenarla: las
 * unidades vuelven a estar disponibles, se borran sus movimientos de Kardex
 * y los equipos que creó (nunca se entregaron), sus líneas y sus cuotas. La
 * venta conserva su número.
 */
class LiberarBorrador
{
    public function handle(Sale $sale): void
    {
        if ($sale->estado !== 'borrador') {
            throw ValidationException::withMessages([
                'estado' => 'Solo se puede editar una venta en borrador. Si ya se emitió, corrígela desde su detalle.',
            ]);
        }

        $sale->load('items');

        $unidades = $sale->items->pluck('inventory_unit_id')->filter()->all();
        $equipos = $sale->items->where('tipo_linea', 'unidad_nueva')->pluck('equipment_id')->filter()->all();

        InventoryUnit::query()->whereIn('id', $unidades)->update(['estado' => 'disponible']);

        InventoryMovement::query()
            ->where('referencia_type', $sale->getMorphClass())
            ->where('referencia_id', $sale->id)
            ->delete();

        $sale->items()->delete();
        Equipment::query()->whereIn('id', $equipos)->delete();
        $sale->installments()->delete();
    }
}
