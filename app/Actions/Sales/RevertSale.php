<?php

namespace App\Actions\Sales;

use App\Models\Certificate;
use App\Models\InventoryMovement;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleRefund;
use App\Services\AuditLogger;
use App\Services\Inventory\StockPorLote;
use Illuminate\Support\Facades\DB;

class RevertSale
{
    /**
     * Anula una venta por una nota de crédito de anulación total: las unidades
     * vuelven al stock con su movimiento de Kardex, los equipos quedan de baja
     * y las cuotas sin cobrar se eliminan. Sus certificados quedan anulados
     * (el QR ya no los muestra como válidos). Los pagos ya registrados no se
     * tocan: el dinero se devuelve al cliente de forma manual.
     */
    public function handle(Sale $sale, string $motivo = 'por nota de crédito'): Sale
    {
        // Bloqueada y releída: dos anulaciones a la vez no deben devolver dos
        // veces el stock ni duplicar la devolución del cobro.
        return DB::transaction(function () use ($sale, $motivo): Sale {
            $bloqueada = Sale::query()->lockForUpdate()->findOrFail($sale->id);

            if ($bloqueada->estado === 'anulada') {
                return $bloqueada;
            }

            return $this->revertir($bloqueada, $motivo);
        });
    }

    protected function revertir(Sale $sale, string $motivo): Sale
    {
        $sale->loadMissing('items.inventoryUnit', 'items.equipment');

        foreach ($sale->items as $item) {
            /** @var SaleItem $item */
            if ($item->tipo_linea === 'producto') {
                $this->devolverProducto($sale, $item, $motivo);

                continue;
            }

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

        Certificate::query()
            ->where('sale_id', $sale->id)
            ->whereIn('estado', ['vigente', 'vencido'])
            ->update([
                'estado' => 'anulado',
                'anulado_motivo' => "Venta {$sale->numero_interno} anulada {$motivo}.",
                'anulado_at' => now(),
                'anulado_por' => auth()->id(),
            ]);

        // Una cuota parcialmente cobrada tambien se cierra: si quedara viva
        // sobre la venta anulada seria una deuda fantasma en Cobranzas. Sus
        // pagos no se tocan (la FK es nullOnDelete) y se devuelven abajo.
        $sale->installments()->whereIn('estado', ['pendiente', 'vencido', 'parcial'])->delete();

        // Lo cobrado (al contado o de sus cuotas), menos lo ya devuelto, se
        // devuelve con la venta: se registra hoy como una devolución, así sale
        // de la caja del turno en que se entrega el dinero y el arqueo del
        // turno original no cambia.
        $yaDevuelto = $sale->refunds()->get()
            ->groupBy('forma_pago')
            ->map(fn ($devoluciones) => (float) $devoluciones->sum('monto'));

        $devoluciones = $sale->payments()->get()
            ->groupBy('forma_pago')
            ->map(fn ($pagos, $forma) => round((float) $pagos->sum('monto') - (float) $yaDevuelto->get($forma, 0), 2))
            ->filter(fn (float $monto) => $monto > 0);

        foreach ($devoluciones as $forma => $monto) {
            SaleRefund::create([
                'sale_id' => $sale->id,
                'forma_pago' => $forma,
                'monto' => $monto,
                'motivo' => "Anulación de la venta {$motivo}",
                'user_id' => auth()->id(),
                'fecha' => today(),
            ]);
        }

        $cobroDevuelto = round((float) $devoluciones->sum(), 2);

        $sale->update(['estado' => 'anulada']);

        AuditLogger::log(
            action: 'venta.anulada',
            entity: $sale,
            newValues: ['estado' => 'anulada', 'motivo' => $motivo, 'cobro_contado_devuelto' => $cobroDevuelto, 'pagos_registrados' => $sale->payments()->exists()],
            userId: auth()->id()
        );

        return $sale;
    }

    /**
     * Devuelve al almacén el stock de un producto sin serie, en la misma sede
     * de donde salió.
     */
    protected function devolverProducto(Sale $sale, SaleItem $item, string $motivo): void
    {
        // Vuelve al mismo almacén y al mismo lote de donde salió.
        app(StockPorLote::class)->devolver($sale, (int) $item->product_id, (int) $item->cantidad, [
            'tipo' => 'ingreso',
            'referencia_type' => $sale->getMorphClass(),
            'referencia_id' => $sale->id,
            'user_id' => auth()->id(),
            'observacion' => "Anulación de la venta {$sale->numero_interno} {$motivo}",
        ], sedeIdSinRastro: $sale->sede_id);
    }
}
