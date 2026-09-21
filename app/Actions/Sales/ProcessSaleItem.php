<?php

namespace App\Actions\Sales;

use App\Models\Equipment;
use App\Models\InventoryMovement;
use App\Models\InventoryUnit;
use App\Models\Sale;
use App\Models\SaleItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProcessSaleItem
{
    /**
     * @param  array<string, mixed>  $itemData
     */
    public function handle(Sale $sale, array $itemData): SaleItem
    {
        return DB::transaction(function () use ($sale, $itemData) {
            if ($itemData['tipo_linea'] === 'recarga_servicio') {
                return $this->processServiceRecharge($sale, $itemData);
            }

            return $this->processNewUnit($sale, $itemData);
        });
    }

    /**
     * @param  array<string, mixed>  $itemData
     */
    protected function processNewUnit(Sale $sale, array $itemData): SaleItem
    {
        if (! $sale->sede_id) {
            throw ValidationException::withMessages([
                'sede_id' => 'La sede de venta es obligatoria para vender una unidad nueva.',
            ]);
        }

        $unit = InventoryUnit::query()
            ->where('numero_serie', $itemData['numero_serie'])
            ->lockForUpdate()
            ->first();

        if (! $unit) {
            throw ValidationException::withMessages([
                'items' => 'No se encontró ninguna unidad con esa serie.',
            ]);
        }

        if (! $unit->estaDisponible()) {
            throw ValidationException::withMessages([
                'items' => "La unidad {$unit->numero_serie} ya no está disponible (estado: {$unit->estado}).",
            ]);
        }

        if ($unit->sede_almacen_id !== (int) $sale->sede_id) {
            throw ValidationException::withMessages([
                'items' => 'La unidad escaneada no pertenece a la sede de la venta.',
            ]);
        }

        if ($unit->catalog_item_id !== (int) $itemData['catalog_item_id']) {
            throw ValidationException::withMessages([
                'items' => 'La unidad escaneada no corresponde al producto seleccionado.',
            ]);
        }

        $this->recordInventoryMovement($sale, $unit);

        $unit->update(['estado' => 'vendido']);

        $equipment = Equipment::create([
            'client_id' => $sale->client_id,
            'catalog_item_id' => $unit->catalog_item_id,
            'numero_serie' => $unit->numero_serie,
            'fecha_venta' => $sale->fecha,
            'estado' => 'activo',
            'proxima_fecha_atencion' => $sale->fecha->copy()->addYear(),
            'proxima_prueba_hidrostatica' => null,
        ]);

        return $sale->items()->create([
            'catalog_item_id' => $itemData['catalog_item_id'],
            'tipo_linea' => 'unidad_nueva',
            'inventory_unit_id' => $unit->id,
            'equipment_id' => $equipment->id,
            'cantidad' => $itemData['cantidad'],
            'precio_unitario' => $itemData['precio_unitario'],
            'descuento' => $itemData['descuento'] ?? 0,
            'subtotal' => $itemData['subtotal'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $itemData
     */
    protected function processServiceRecharge(Sale $sale, array $itemData): SaleItem
    {
        $equipment = Equipment::query()
            ->where('numero_serie', $itemData['numero_serie'])
            ->where('client_id', $sale->client_id)
            ->first();

        if (! $equipment) {
            throw ValidationException::withMessages([
                'items' => 'El equipo indicado no existe o no pertenece a este cliente.',
            ]);
        }

        return $sale->items()->create([
            'catalog_item_id' => $itemData['catalog_item_id'],
            'tipo_linea' => 'recarga_servicio',
            'inventory_unit_id' => null,
            'equipment_id' => $equipment->id,
            'cantidad' => $itemData['cantidad'],
            'precio_unitario' => $itemData['precio_unitario'],
            'descuento' => $itemData['descuento'] ?? 0,
            'subtotal' => $itemData['subtotal'],
        ]);
    }

    protected function recordInventoryMovement(Sale $sale, InventoryUnit $unit): void
    {
        $movement = new InventoryMovement;
        $movement->inventory_unit_id = $unit->id;
        $movement->catalog_item_id = $unit->catalog_item_id;
        $movement->sede_id = $unit->sede_almacen_id;
        $movement->tipo = 'salida_venta';
        $movement->cantidad = 1;
        $movement->referencia_type = $sale->getMorphClass();
        $movement->referencia_id = $sale->id;
        $movement->user_id = $sale->vendedor_id;
        $movement->observacion = "Venta {$sale->numero_interno}";
        $movement->save();
    }
}
