<?php

namespace App\Actions\Almacen;

use App\Models\InventoryMovement;
use App\Models\InventoryUnit;
use App\Models\Product;
use App\Models\Reception;
use App\Models\ReceptionItem;
use App\Models\User;
use App\Services\Inventory\InventorySequenceGenerator;
use App\Services\Inventory\StockPorLote;
use Illuminate\Support\Facades\DB;

class CreateReception
{
    public function __construct(
        protected InventorySequenceGenerator $sequenceGenerator,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @param  list<array<string, mixed>>  $items
     */
    public function handle(array $data, array $items, ?User $user = null): Reception
    {
        return DB::transaction(function () use ($data, $items, $user) {
            $reception = Reception::create([
                'proveedor' => $data['proveedor'],
                'documento_referencia' => $data['documento_referencia'] ?? null,
                'fecha' => $data['fecha'],
                'sede_almacen_id' => $data['sede_almacen_id'],
                'user_id' => $user?->id ?? $data['user_id'] ?? null,
                'observacion' => $data['observacion'] ?? null,
            ]);

            foreach ($items as $itemData) {
                /** @var ReceptionItem $item */
                $item = $reception->items()->create([
                    'product_id' => $itemData['product_id'],
                    'cantidad' => $itemData['cantidad'],
                    'cantidad_conforme' => $itemData['cantidad_conforme'],
                    'observacion_item' => $itemData['observacion_item'] ?? null,
                    'lote' => filled($itemData['lote'] ?? null) ? mb_strtoupper(trim((string) $itemData['lote'])) : null,
                    'fecha_vencimiento' => $itemData['fecha_vencimiento'] ?? null,
                ]);

                $conforme = (int) $itemData['cantidad_conforme'];
                if ($conforme <= 0) {
                    // Si no hubo cantidad conforme, no entra al stock ni crea movimientos
                    continue;
                }

                $product = Product::query()->findOrFail((int) $itemData['product_id']);

                if ($product->serializado) {
                    // Para producto serializado: crear N InventoryUnit + N InventoryMovement (cantidad = 1)
                    $unidadesData = $itemData['unidades'] ?? [];
                    for ($i = 0; $i < $conforme; $i++) {
                        $uData = $unidadesData[$i] ?? [];
                        $numeroSerie = $this->sequenceGenerator->nextEquipmentSerial();

                        $unit = InventoryUnit::create([
                            'product_id' => $product->id,
                            'sede_almacen_id' => $reception->sede_almacen_id,
                            'numero_serie' => $numeroSerie,
                            'capacidad' => $uData['capacidad'] ?? $product->capacidad,
                            'agente' => $product->agente,
                            'serie_fabricante' => $uData['serie_fabricante'] ?? null,
                            'marca' => $uData['marca'] ?? null,
                            'anio_fabricacion' => $uData['anio_fabricacion'] ?? null,
                            'estado' => 'disponible',
                            'fecha_ingreso' => $reception->fecha,
                        ]);

                        $movement = new InventoryMovement([
                            'inventory_unit_id' => $unit->id,
                            'product_id' => $product->id,
                            'sede_id' => $reception->sede_almacen_id,
                            'tipo' => 'ingreso',
                            'cantidad' => 1,
                            'user_id' => $reception->user_id,
                            'observacion' => "Recepción {$reception->id} - {$reception->proveedor} (Serie {$numeroSerie})",
                        ]);
                        $movement->referencia_type = $reception->getMorphClass();
                        $movement->referencia_id = $reception->id;
                        $movement->save();
                    }
                } else {
                    // Producto sin serie: un movimiento con la cantidad; si lleva
                    // lote, entra a su lote con su vencimiento.
                    $movement = app(StockPorLote::class)->ingresar($product, (int) $reception->sede_almacen_id, $conforme, [
                        'tipo' => 'ingreso',
                        'user_id' => $reception->user_id,
                        'observacion' => "Recepción {$reception->id} - {$reception->proveedor}",
                        'referencia_type' => $reception->getMorphClass(),
                        'referencia_id' => $reception->id,
                    ], $item->lote, $item->fecha_vencimiento?->toDateString(), 'items');

                    if ($movement->product_lot_id) {
                        $item->update(['product_lot_id' => $movement->product_lot_id]);
                    }
                }
            }

            return $reception->load(['items.product', 'sedeAlmacen', 'user', 'movements']);
        });
    }
}
