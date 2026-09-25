<?php

namespace App\Actions\Sales;

use App\Models\InventoryMovement;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Services\AuditLogger;

class RevertSale
{
    /**
     * Anula una venta por una nota de crédito de anulación total: las unidades
     * vuelven al stock con su movimiento de Kardex, los equipos quedan de baja
     * y las cuotas sin cobrar se eliminan. Los pagos ya registrados no se
     * tocan: el dinero se devuelve al cliente de forma manual.
     */
    public function handle(Sale $sale, string $motivo = 'por nota de crédito'): Sale
    {
        $sale->loadMissing('items.inventoryUnit', 'items.equipment');

        foreach ($sale->items as $item) {
            /** @var SaleItem $item */
            if ($item->tipo_linea !== 'unidad_nueva' || ! $item->inventoryUnit) {
                continue;
            }

            $item->inventoryUnit->update(['estado' => 'disponible']);
            $item->equipment?->update(['estado' => 'baja']);

            $movement = new InventoryMovement;
            $movement->inventory_unit_id = $item->inventoryUnit->id;
            $movement->product_id = $item->inventoryUnit->product_id;
            $movement->sede_id = $item->inventoryUnit->sede_almacen_id;
            $movement->tipo = 'ingreso';
            $movement->cantidad = 1;
            $movement->referencia_type = $sale->getMorphClass();
            $movement->referencia_id = $sale->id;
            $movement->user_id = auth()->id();
            $movement->observacion = "Anulación de la venta {$sale->numero_interno} {$motivo}";
            $movement->save();
        }

        $sale->installments()->whereIn('estado', ['pendiente', 'vencido'])->delete();
        $sale->update(['estado' => 'anulada']);

        AuditLogger::log(
            action: 'venta.anulada',
            entity: $sale,
            newValues: ['estado' => 'anulada', 'motivo' => $motivo, 'pagos_registrados' => $sale->payments()->exists()],
            userId: auth()->id()
        );

        return $sale;
    }
}
