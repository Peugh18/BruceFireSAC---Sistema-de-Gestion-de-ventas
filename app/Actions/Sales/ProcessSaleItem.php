<?php

namespace App\Actions\Sales;

use App\Models\Certificate;
use App\Models\Equipment;
use App\Models\InventoryMovement;
use App\Models\InventoryUnit;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Sede;
use App\Models\Service;
use App\Services\Billing\AfectacionIgv;
use App\Services\Inventory\StockPorLote;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProcessSaleItem
{
    /**
     * Líneas que se venden por número de serie: una serie, una unidad (V1).
     *
     * @var list<string>
     */
    public const LINEAS_POR_SERIE = ['unidad_nueva', 'recarga_servicio'];

    public const MENSAJE_CANTIDAD_POR_SERIE = 'Cada serie es una unidad: la cantidad de esta línea debe ser 1. Para vender más, agrega cada serie.';

    /**
     * @param  array<string, mixed>  $itemData
     */
    public function handle(Sale $sale, array $itemData): SaleItem
    {
        if (in_array($itemData['tipo_linea'], self::LINEAS_POR_SERIE, true) && (int) $itemData['cantidad'] !== 1) {
            throw ValidationException::withMessages(['items' => self::MENSAJE_CANTIDAD_POR_SERIE]);
        }

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
        $product = Product::query()->findOrFail((int) $itemData['product_id']);

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

        // Sin dejar el almacén en negativo; si lleva lote, sale lo que vence
        // primero y nunca un lote vencido.
        app(StockPorLote::class)->sacar($product, $almacenId, $cantidad, [
            'tipo' => 'salida_venta',
            'referencia_type' => $sale->getMorphClass(),
            'referencia_id' => $sale->id,
            'user_id' => $sale->vendedor_id,
            'observacion' => "Venta {$sale->numero_interno}",
        ], campo: 'items', verbo: 'vender');

        return $sale->items()->create([
            'product_id' => $product->id,
            'tipo_afectacion_igv' => AfectacionIgv::codigo($product),
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
        $service = Service::query()->findOrFail((int) $itemData['service_id']);

        return $sale->items()->create([
            'service_id' => $service->id,
            'tipo_afectacion_igv' => AfectacionIgv::codigo($service),
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

        // La ficha del equipo es de UN cliente y no se transfiere: las otras
        // acciones del sistema (QuickRegisterEquipment, EquipoDeLaOrden) se niegan
        // a tocar un equipo de otro cliente. Reasignarla en silencio arrastraba
        // los certificados, checklists y órdenes del cliente anterior al nuevo.
        // Solo se reutiliza cuando la propiedad anterior está totalmente cerrada
        // (venta anulada y sus certificados anulados, que es lo que deja
        // RevertSale). Si quedó historial vivo, no se roba: se explica y no se
        // vende la serie.
        $equipment = Equipment::query()->where('numero_serie', $unit->numero_serie)->first();

        if ($equipment && (int) $equipment->client_id !== (int) $sale->client_id) {
            $this->exigirPropiedadAnteriorCerrada($equipment);
        }

        $equipment = Equipment::updateOrCreate(['numero_serie' => $unit->numero_serie], [
            'client_id' => $sale->client_id,
            'product_id' => $unit->product_id,
            'tipo_agente' => $unit->agenteParaEquipo(),
            'capacidad' => $unit->capacidad ?? $unit->product->capacidad,
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
            'tipo_afectacion_igv' => AfectacionIgv::codigo($unit->product),
            'service_id' => null,
            'tipo_linea' => 'unidad_nueva',
            'inventory_unit_id' => $unit->id,
            'equipment_id' => $equipment->id,
            'cantidad' => 1,
            'precio_unitario' => $itemData['precio_unitario'],
            'descuento' => $itemData['descuento'] ?? 0,
            'subtotal' => $itemData['subtotal'],
        ]);
    }

    /**
     * La ficha del equipo pertenece a un cliente y su historial técnico no se
     * transfiere al siguiente comprador: moverla en silencio dejaba certificados
     * de un cliente colgando del equipo de otro. Si la propiedad anterior dejó
     * certificados vivos, checklists u órdenes de servicio, no se reutiliza la
     * ficha.
     */
    private function exigirPropiedadAnteriorCerrada(Equipment $equipment): void
    {
        $certificadosVivos = Certificate::query()
            ->whereHas('certificateUnits', fn ($query) => $query->where('equipment_id', $equipment->id))
            ->where('estado', '!=', 'anulado')
            ->exists();

        if (! $certificadosVivos && ! $equipment->checklists()->exists() && ! $equipment->serviceOrders()->exists()) {
            return;
        }

        throw ValidationException::withMessages([
            'items' => "La serie {$equipment->numero_serie} tiene certificados u órdenes de servicio de otro cliente: no se puede vender a un cliente distinto mientras ese historial esté vivo.",
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
            ? Service::query()->whereKey($itemData['service_id'])->first()
            : null;

        // V5: la fecha de la próxima atención se renueva al cerrar el trabajo
        // técnico de la orden (certificado o entrega), nunca al guardar la
        // venta: un borrador o una venta descartada no acreditan la recarga.

        return $sale->items()->create([
            'product_id' => null,
            'service_id' => $itemData['service_id'] ?? null,
            'tipo_afectacion_igv' => $service ? AfectacionIgv::codigo($service) : '10',
            'tipo_linea' => 'recarga_servicio',
            'inventory_unit_id' => null,
            'equipment_id' => $equipment->id,
            'cantidad' => 1,
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
