<?php

namespace App\Actions\Sales;

use App\Actions\Equipment\RenewEquipmentAttentionDate;
use App\Models\Equipment;
use App\Models\InventoryMovement;
use App\Models\InventoryUnit;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Sede;
use App\Models\Service;
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
            return match ($itemData['tipo_linea']) {
                'recarga_servicio' => $this->processServiceRecharge($sale, $itemData),
                'producto' => $this->processProducto($sale, $itemData),
                'servicio' => $this->processServicio($sale, $itemData),
                default => $this->processNewUnit($sale, $itemData),
            };
        });
    }

    /**
     * Producto sin serie (bases, soportes, repuestos): se vende por cantidad
     * y descuenta ese stock del almacén de la sede con un movimiento de Kardex.
     *
     * @param  array<string, mixed>  $itemData
     */
    protected function processProducto(Sale $sale, array $itemData): SaleItem
    {
        $product = Product::query()->findOrFail($itemData['product_id']);

        if ($product->serializado) {
            throw ValidationException::withMessages([
                'items' => "{$product->nombre} se vende por número de serie: escanea o elige cada unidad.",
            ]);
        }

        if (! $sale->sede_id) {
            throw ValidationException::withMessages([
                'sede_id' => 'La sede de venta es obligatoria para vender productos con stock.',
            ]);
        }

        $almacenId = Sede::find($sale->sede_id)?->almacenEfectivoId() ?? (int) $sale->sede_id;
        $cantidad = (int) $itemData['cantidad'];
        $stock = (int) InventoryMovement::query()
            ->where('product_id', $product->id)
            ->where('sede_id', $almacenId)
            ->lockForUpdate()
            ->sum('cantidad');

        if ($stock < $cantidad) {
            throw ValidationException::withMessages([
                'items' => "Stock insuficiente de {$product->nombre}: hay {$stock} y se quieren vender {$cantidad}.",
            ]);
        }

        $movement = new InventoryMovement;
        $movement->product_id = $product->id;
        $movement->sede_id = $almacenId;
        $movement->tipo = 'salida_venta';
        $movement->cantidad = -$cantidad;
        $movement->referencia_type = $sale->getMorphClass();
        $movement->referencia_id = $sale->id;
        $movement->user_id = $sale->vendedor_id;
        $movement->observacion = "Venta {$sale->numero_interno}";
        $movement->save();

        return $sale->items()->create([
            'product_id' => $product->id,
            'tipo_linea' => 'producto',
            'cantidad' => $cantidad,
            'precio_unitario' => $itemData['precio_unitario'],
            'descuento' => $itemData['descuento'] ?? 0,
            'subtotal' => $itemData['subtotal'],
        ]);
    }

    /**
     * Servicio suelto (instalación, recarga de equipos que el cliente trae
     * sin registrar, capacitación): no mueve stock.
     *
     * @param  array<string, mixed>  $itemData
     */
    protected function processServicio(Sale $sale, array $itemData): SaleItem
    {
        $service = Service::query()->findOrFail($itemData['service_id']);

        return $sale->items()->create([
            'service_id' => $service->id,
            'tipo_linea' => 'servicio',
            'cantidad' => (int) $itemData['cantidad'],
            'precio_unitario' => $itemData['precio_unitario'],
            'descuento' => $itemData['descuento'] ?? 0,
            'subtotal' => $itemData['subtotal'],
        ]);
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

        $almacenId = Sede::find($sale->sede_id)?->almacenEfectivoId() ?? (int) $sale->sede_id;

        if ($unit->sede_almacen_id !== $almacenId) {
            throw ValidationException::withMessages([
                'items' => 'La unidad escaneada no pertenece a la sede de la venta.',
            ]);
        }

        $expectedProductId = (int) ($itemData['product_id'] ?? 0);
        if ($unit->product_id !== $expectedProductId) {
            throw ValidationException::withMessages([
                'items' => 'La unidad escaneada no corresponde al producto seleccionado.',
            ]);
        }

        $this->recordInventoryMovement($sale, $unit);

        $unit->update(['estado' => 'vendido']);

        // Si la unidad ya se vendió antes y esa venta se anuló, su equipo
        // quedó de baja con la misma serie: se reutiliza para el nuevo cliente.
        $equipment = Equipment::updateOrCreate(['numero_serie' => $unit->numero_serie], [
            'client_id' => $sale->client_id,
            'product_id' => $unit->product_id,
            'capacidad' => $unit->capacidad,
            'marca' => $unit->marca,
            'serie_fabricante' => $unit->serie_fabricante,
            'anio_fabricacion' => $unit->anio_fabricacion,
            'fecha_venta' => $sale->fecha,
            'estado' => 'activo',
            'proxima_fecha_atencion' => $sale->fecha->copy()->addYear(),
            'proxima_prueba_hidrostatica' => $sale->fecha->copy()->addYears(5),
        ]);

        return $sale->items()->create([
            'product_id' => $unit->product_id,
            'service_id' => null,
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

        $service = ! empty($itemData['service_id'])
            ? Service::with('certificateType')->whereKey($itemData['service_id'])->first()
            : null;

        $esPH = $service && (
            $service->certificateType?->codigo === 'prueba_hidrostatica'
            || str_contains(mb_strtoupper($service->nombre), 'PRUEBA HIDROST')
            || str_contains(mb_strtoupper($service->nombre), 'P.H.')
        );

        app(RenewEquipmentAttentionDate::class)->execute(collect([$equipment]), (bool) $esPH);

        return $sale->items()->create([
            'product_id' => null,
            'service_id' => $itemData['service_id'] ?? null,
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
        $movement->product_id = $unit->product_id;
        $movement->sede_id = $unit->sede_almacen_id;
        $movement->tipo = 'salida_venta';
        // Las salidas restan, como en el resto del Kardex.
        $movement->cantidad = -1;
        $movement->referencia_type = $sale->getMorphClass();
        $movement->referencia_id = $sale->id;
        $movement->user_id = $sale->vendedor_id;
        $movement->observacion = "Venta {$sale->numero_interno}";
        $movement->save();
    }
}
