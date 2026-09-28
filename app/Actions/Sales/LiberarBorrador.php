<?php

namespace App\Actions\Sales;

use App\Models\CertificateUnit;
use App\Models\Equipment;
use App\Models\InventoryMovement;
use App\Models\InventoryUnit;
use App\Models\Sale;
use App\Models\SaleItem;
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

        // El equipo que nació con este borrador se borra; si ya tenía historia
        // (una venta anterior anulada o certificados), solo queda de baja.
        foreach (Equipment::query()->whereIn('id', $equipos)->get() as $equipo) {
            $conHistoria = SaleItem::query()->where('equipment_id', $equipo->id)->exists()
                || CertificateUnit::query()->where('equipment_id', $equipo->id)->exists();

            $conHistoria ? $equipo->update(['estado' => 'baja']) : $equipo->delete();
        }
        $sale->installments()->delete();
    }
}
