<?php

namespace App\Actions\Almacen;

use App\Models\InventoryMovement;
use App\Models\InventoryUnit;
use App\Models\ProductLot;
use App\Models\Reception;
use App\Models\ReceptionItem;
use App\Models\User;
use App\Services\Inventory\InventorySequenceGenerator;
use App\Services\Inventory\StockPorLote;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateReception
{
    public function __construct(
        protected InventorySequenceGenerator $sequenceGenerator,
    ) {}

    /**
     * Actualiza una recepción confirmada.
     * Regla de negocio §84.8:
     * Si se corrige cantidad_conforme, ajusta con un movimiento compensatorio nuevo (tipo 'ajuste'),
     * nunca edita un InventoryMovement histórico ya guardado.
     *
     * @param  array<string, mixed>  $data
     * @param  list<array<string, mixed>>  $itemsData
     */
    public function handle(Reception $reception, array $data, array $itemsData, ?User $user = null): Reception
    {
        return DB::transaction(function () use ($reception, $data, $itemsData, $user) {
            $reception->update([
                'proveedor' => $data['proveedor'],
                'documento_referencia' => $data['documento_referencia'] ?? null,
                'fecha' => $data['fecha'],
                'observacion' => $data['observacion'] ?? null,
            ]);

            $userId = $user?->id ?? $reception->user_id;

            foreach ($itemsData as $itemData) {
                /** @var ReceptionItem $item */
                // Solo partidas de esta recepción, bloqueadas mientras se corrigen.
                $item = $reception->items()->lockForUpdate()->findOrFail($itemData['id']);
                $prevConforme = (int) $item->cantidad_conforme;
                $newConforme = (int) $itemData['cantidad_conforme'];
                $diff = $newConforme - $prevConforme;

                $item->update([
                    'cantidad' => $itemData['cantidad'],
                    'cantidad_conforme' => $newConforme,
                    'observacion_item' => $itemData['observacion_item'] ?? null,
                ]);

                // Vencimiento mal tipeado: se corrige en el lote.
                if (! empty($itemData['fecha_vencimiento']) && $item->product_lot_id) {
                    $item->update(['fecha_vencimiento' => $itemData['fecha_vencimiento']]);
                    ProductLot::query()->whereKey($item->product_lot_id)->update(['fecha_vencimiento' => $itemData['fecha_vencimiento']]);
                }

                if ($diff === 0) {
                    continue;
                }

                $product = $item->product;

                if ($product->serializado) {
                    if ($diff > 0) {
                        // Se aumentó la cantidad conforme: crear las nuevas unidades y registrar movimiento compensatorio
                        $unidadesNuevas = $itemData['unidades_nuevas'] ?? [];
                        if (count($unidadesNuevas) < $diff) {
                            throw ValidationException::withMessages([
                                'items' => "Faltan los datos de {$diff} unidad(es) nueva(s) de {$product->nombre} (marca, capacidad y año).",
                            ]);
                        }
                        for ($i = 0; $i < $diff; $i++) {
                            $uData = $unidadesNuevas[$i] ?? [];
                            $numeroSerie = $this->sequenceGenerator->nextEquipmentSerial();

                            $unit = InventoryUnit::create([
                                'product_id' => $product->id,
                                'sede_almacen_id' => $reception->sede_almacen_id,
                                'numero_serie' => $numeroSerie,
                                'capacidad' => $uData['capacidad'] ?? null,
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
                                'tipo' => 'ajuste',
                                'cantidad' => 1,
                                'user_id' => $userId,
                                'observacion' => "Corrección Recepción {$reception->id}: +1 unidad conforme (Serie {$numeroSerie})",
                            ]);
                            $movement->referencia_type = $reception->getMorphClass();
                            $movement->referencia_id = $reception->id;
                            $movement->save();
                        }
                    } else {
                        // Se redujo la cantidad conforme ($diff < 0):
                        // Dar de baja o marcar no disponible a las últimas unidades recibidas en esta recepción que sigan disponibles
                        $toRemove = abs($diff);
                        $availableUnits = InventoryUnit::query()
                            ->where('product_id', $product->id)
                            ->where('sede_almacen_id', $reception->sede_almacen_id)
                            ->where('estado', 'disponible')
                            ->whereHas('movements', function ($q) use ($reception) {
                                $q->where('referencia_type', $reception->getMorphClass())
                                    ->where('referencia_id', $reception->id);
                            })
                            ->latest('id')
                            ->limit($toRemove)
                            ->get();

                        // Las que ya se vendieron no se pueden retirar.
                        if ($availableUnits->count() < $toRemove) {
                            throw ValidationException::withMessages([
                                'items' => "Solo {$availableUnits->count()} unidad(es) de {$product->nombre} de esta recepción siguen en el almacén: no se pueden retirar {$toRemove}.",
                            ]);
                        }

                        foreach ($availableUnits as $unit) {
                            $unit->update(['estado' => 'baja']);

                            $movement = new InventoryMovement([
                                'inventory_unit_id' => $unit->id,
                                'product_id' => $product->id,
                                'sede_id' => $reception->sede_almacen_id,
                                'tipo' => 'ajuste',
                                'cantidad' => -1,
                                'user_id' => $userId,
                                'observacion' => "Corrección Recepción {$reception->id}: -1 unidad retirada por no conformidad (Serie {$unit->numero_serie})",
                            ]);
                            $movement->referencia_type = $reception->getMorphClass();
                            $movement->referencia_id = $reception->id;
                            $movement->save();
                        }
                    }
                } else {
                    // Producto sin serie: movimiento compensatorio 'ajuste' con la
                    // diferencia, en el mismo lote de la partida si lleva lote.
                    $datos = [
                        'tipo' => 'ajuste',
                        'user_id' => $userId,
                        'observacion' => "Corrección Recepción {$reception->id}: ".($diff > 0 ? "+{$diff}" : (string) $diff).' conforme',
                        'referencia_type' => $reception->getMorphClass(),
                        'referencia_id' => $reception->id,
                    ];
                    $stock = app(StockPorLote::class);

                    if ($diff < 0) {
                        $stock->sacar($product, (int) $reception->sede_almacen_id, -$diff, $datos, 'items', $item->product_lot_id, 'retirar');
                    } else {
                        $movement = $stock->ingresar($product, (int) $reception->sede_almacen_id, $diff, $datos, $item->lote, $item->fecha_vencimiento?->toDateString(), 'items');
                        if ($movement->product_lot_id && ! $item->product_lot_id) {
                            $item->update(['product_lot_id' => $movement->product_lot_id]);
                        }
                    }
                }
            }

            return $reception->fresh(['items.product', 'sedeAlmacen', 'user', 'movements']);
        });
    }
}
