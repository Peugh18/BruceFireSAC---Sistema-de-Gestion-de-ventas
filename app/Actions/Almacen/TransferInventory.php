<?php

namespace App\Actions\Almacen;

use App\Models\InventoryMovement;
use App\Models\InventoryTransfer;
use App\Models\InventoryUnit;
use App\Models\Product;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Inventory\StockPorLote;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TransferInventory
{
    /**
     * Registra el traslado: el stock sale del origen y queda "en tránsito"
     * hasta que el almacén destino confirme la llegada (confirmar()).
     *
     * @param  array{destination_sede_id:int, serials?:list<string>, product_id?:int, quantity?:int, observation?:string|null}  $data
     */
    public function handle(int $sourceSedeId, array $data, User $user): InventoryTransfer
    {
        if ($sourceSedeId === $data['destination_sede_id']) {
            throw ValidationException::withMessages(['destination_sede_id' => 'La sede destino debe ser diferente al origen.']);
        }

        return DB::transaction(function () use ($sourceSedeId, $data, $user): InventoryTransfer {
            $traslado = InventoryTransfer::create([
                'origen_sede_id' => $sourceSedeId,
                'destino_sede_id' => $data['destination_sede_id'],
                'user_id' => $user->id,
                'estado' => InventoryTransfer::EN_TRANSITO,
                'observacion' => $data['observation'] ?? null,
            ]);
            $datos = $this->datos($traslado, $user, trim('Traslado entre sedes (en tránsito). '.($data['observation'] ?? '')));

            $serials = collect($data['serials'] ?? [])->filter()->values();
            if ($serials->isNotEmpty()) {
                $units = InventoryUnit::query()->whereIn('numero_serie', $serials)->lockForUpdate()->get();
                if ($units->count() !== $serials->count() || $units->contains(fn (InventoryUnit $unit): bool => $unit->sede_almacen_id !== $sourceSedeId || ! $unit->estaDisponible())) {
                    throw ValidationException::withMessages(['serials' => 'Todas las unidades deben estar disponibles en la sede origen.']);
                }
                foreach ($units as $unit) {
                    $salida = $this->movimiento($unit->product_id, $sourceSedeId, -1, $datos, $unit);
                    $unit->update(['estado' => 'en_transito']);
                    $traslado->items()->create(['product_id' => $unit->product_id, 'inventory_movement_id' => $salida->id, 'inventory_unit_id' => $unit->id, 'cantidad' => 1]);
                }
            } else {
                $productId = $data['product_id'] ?? null;
                $quantity = $data['quantity'] ?? null;
                if ($productId === null || $quantity === null) {
                    throw ValidationException::withMessages(['product_id' => 'Selecciona un producto y una cantidad.']);
                }
                $product = Product::query()->where('serializado', false)->findOrFail($productId);
                foreach (app(StockPorLote::class)->sacar($product, $sourceSedeId, (int) $quantity, $datos, 'quantity', verbo: 'trasladar') as $salida) {
                    $traslado->items()->create(['product_id' => $product->id, 'inventory_movement_id' => $salida->id, 'cantidad' => abs($salida->cantidad)]);
                }
            }

            AuditLogger::log('inventario.traslado', $traslado, ['sede_id' => $sourceSedeId], [
                'sede_id' => $data['destination_sede_id'],
                'serials' => $serials->all(),
                'product_id' => $data['product_id'] ?? null,
                'quantity' => $data['quantity'] ?? $serials->count(),
                'estado' => InventoryTransfer::EN_TRANSITO,
            ], userId: $user->id);

            return $traslado;
        });
    }

    /**
     * El almacén destino confirma la llegada: recién ahí el stock entra a su
     * almacén (en el mismo lote y vencimiento) y las unidades quedan
     * disponibles en él.
     */
    public function confirmar(InventoryTransfer $traslado, User $user): InventoryTransfer
    {
        return DB::transaction(function () use ($traslado, $user): InventoryTransfer {
            $traslado = InventoryTransfer::query()->lockForUpdate()->findOrFail($traslado->id);

            if (! $traslado->estaEnTransito()) {
                throw ValidationException::withMessages(['traslado' => 'Este traslado ya fue confirmado.']);
            }

            $datos = $this->datos($traslado, $user, 'Llegada confirmada del traslado entre sedes.');
            $stock = app(StockPorLote::class);

            foreach ($traslado->items()->with('salida.product', 'unit')->get() as $item) {
                if ($item->unit !== null) {
                    $this->movimiento($item->product_id, $traslado->destino_sede_id, 1, $datos, $item->unit);
                    $item->unit->update(['sede_almacen_id' => $traslado->destino_sede_id, 'estado' => 'disponible']);
                } elseif ($item->salida !== null) {
                    $stock->ingresarComo($item->salida, $traslado->destino_sede_id, $datos);
                }
            }

            $traslado->update(['estado' => InventoryTransfer::RECIBIDO, 'recibido_por' => $user->id, 'recibido_at' => now()]);
            AuditLogger::log('inventario.traslado_recibido', $traslado, ['estado' => InventoryTransfer::EN_TRANSITO], ['estado' => InventoryTransfer::RECIBIDO], userId: $user->id);

            return $traslado;
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function datos(InventoryTransfer $traslado, User $user, string $observacion): array
    {
        return [
            'tipo' => 'traslado',
            'user_id' => $user->id,
            'observacion' => $observacion,
            'referencia_type' => $traslado->getMorphClass(),
            'referencia_id' => $traslado->id,
        ];
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function movimiento(int $productId, int $sedeId, int $cantidad, array $datos, InventoryUnit $unit): InventoryMovement
    {
        return InventoryMovement::create([
            'inventory_unit_id' => $unit->id,
            'product_id' => $productId,
            'sede_id' => $sedeId,
            'tipo' => 'traslado',
            'cantidad' => $cantidad,
            'user_id' => $datos['user_id'],
            'observacion' => $datos['observacion'],
            'referencia_type' => $datos['referencia_type'],
            'referencia_id' => $datos['referencia_id'],
        ]);
    }
}
