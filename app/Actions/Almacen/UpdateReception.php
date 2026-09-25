<?php

namespace App\Actions\Almacen;

use App\Models\InventoryMovement;
use App\Models\InventoryUnit;
use App\Models\Reception;
use App\Models\ReceptionItem;
use App\Models\User;
use App\Services\Inventory\InventorySequenceGenerator;
use Illuminate\Support\Facades\DB;

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
                $item = ReceptionItem::findOrFail($itemData['id']);
                $prevConforme = (int) $item->cantidad_conforme;
                $newConforme = (int) $itemData['cantidad_conforme'];
                $diff = $newConforme - $prevConforme;

                $item->update([
                    'cantidad' => $itemData['cantidad'],
                    'cantidad_conforme' => $newConforme,
                    'observacion_item' => $itemData['observacion_item'] ?? null,
                ]);

                if ($diff === 0) {
                    continue;
                }

                $product = $item->product;

                if ($product->serializado) {
                    if ($diff > 0) {
                        // Se aumentó la cantidad conforme: crear las nuevas unidades y registrar movimiento compensatorio
                        $unidadesNuevas = $itemData['unidades_nuevas'] ?? [];
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
                    // Producto no serializado: insertar movimiento compensatorio tipo 'ajuste' con la diferencia (+ o -)
                    $movement = new InventoryMovement([
                        'inventory_unit_id' => null,
                        'product_id' => $product->id,
                        'sede_id' => $reception->sede_almacen_id,
                        'tipo' => 'ajuste',
                        'cantidad' => $diff,
                        'user_id' => $userId,
                        'observacion' => "Corrección Recepción {$reception->id}: ".($diff > 0 ? "+{$diff}" : (string) $diff).' conforme',
                    ]);
                    $movement->referencia_type = $reception->getMorphClass();
                    $movement->referencia_id = $reception->id;
                    $movement->save();
                }
            }

            return $reception->fresh(['items.product', 'sedeAlmacen', 'user', 'movements']);
        });
    }
}
