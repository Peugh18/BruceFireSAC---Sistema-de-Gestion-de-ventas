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
     * Prepara el traslado sin afectar stock.
     *
     * @param  array{destination_sede_id:int, serials?:list<string>, product_id?:int, quantity?:int, observation?:string|null}  $data
     */
    public function handle(int $sourceSedeId, array $data, User $user): InventoryTransfer
    {
        abort_if($user->almacenRestringidoId() !== null && $user->almacenRestringidoId() !== $sourceSedeId, 403);

        if ($sourceSedeId === $data['destination_sede_id']) {
            throw ValidationException::withMessages(['destination_sede_id' => 'La sede destino debe ser diferente al origen.']);
        }

        return DB::transaction(function () use ($sourceSedeId, $data, $user): InventoryTransfer {
            $traslado = InventoryTransfer::create([
                'origen_sede_id' => $sourceSedeId,
                'destino_sede_id' => $data['destination_sede_id'],
                'user_id' => $user->id,
                'estado' => InventoryTransfer::PENDIENTE_DESPACHO,
                'observacion' => $data['observation'] ?? null,
            ]);

            $serials = collect($data['serials'] ?? [])->filter()->values();
            if ($serials->isNotEmpty()) {
                $units = InventoryUnit::query()->whereIn('numero_serie', $serials)->lockForUpdate()->get();
                if ($units->count() !== $serials->count() || $units->contains(fn (InventoryUnit $unit): bool => $unit->sede_almacen_id !== $sourceSedeId || ! $unit->estaDisponible())) {
                    throw ValidationException::withMessages(['serials' => 'Todas las unidades deben estar disponibles en la sede origen.']);
                }
                foreach ($units as $unit) {
                    $traslado->items()->create(['product_id' => $unit->product_id, 'inventory_unit_id' => $unit->id, 'cantidad' => 1]);
                }
            } else {
                $productId = $data['product_id'] ?? null;
                $quantity = $data['quantity'] ?? null;
                if ($productId === null || $quantity === null) {
                    throw ValidationException::withMessages(['product_id' => 'Selecciona un producto y una cantidad.']);
                }
                $product = Product::query()->where('serializado', false)->findOrFail($productId);
                if ((int) $quantity < 1 || InventoryMovement::saldo($product->id, $sourceSedeId) < (int) $quantity) {
                    throw ValidationException::withMessages(['quantity' => 'Stock insuficiente para preparar el traslado.']);
                }
                $traslado->items()->create(['product_id' => $product->id, 'cantidad' => (int) $quantity]);
            }

            AuditLogger::log('inventario.traslado', $traslado, ['sede_id' => $sourceSedeId], [
                'sede_id' => $data['destination_sede_id'],
                'serials' => $serials->all(),
                'product_id' => $data['product_id'] ?? null,
                'quantity' => $data['quantity'] ?? $serials->count(),
                'estado' => InventoryTransfer::PENDIENTE_DESPACHO,
            ], userId: $user->id);

            return $traslado;
        });
    }

    public function despachar(InventoryTransfer $traslado, User $user): InventoryTransfer
    {
        return DB::transaction(function () use ($traslado, $user): InventoryTransfer {
            $traslado = InventoryTransfer::query()->lockForUpdate()->findOrFail($traslado->id);
            abort_if($user->almacenRestringidoId() !== null && $user->almacenRestringidoId() !== $traslado->origen_sede_id, 403);
            if ($traslado->estado !== InventoryTransfer::PENDIENTE_DESPACHO) {
                throw ValidationException::withMessages(['traslado' => 'Este traslado ya fue despachado.']);
            }
            $datos = $this->datos($traslado, $user, 'Despacho interno entre sedes.');
            foreach ($traslado->items()->orderBy('product_id')->get() as $item) {
                if ($item->inventory_unit_id !== null) {
                    $unit = InventoryUnit::query()->lockForUpdate()->findOrFail($item->inventory_unit_id);
                    if ($unit->sede_almacen_id !== $traslado->origen_sede_id || ! $unit->estaDisponible()) {
                        throw ValidationException::withMessages(['serials' => 'La unidad ya no está disponible en el origen.']);
                    }
                    $salida = $this->movimiento($unit->product_id, $traslado->origen_sede_id, -1, $datos, $unit);
                    $unit->update(['estado' => 'en_transito']);
                    $item->update(['inventory_movement_id' => $salida->id]);

                    continue;
                }
                $product = Product::query()->lockForUpdate()->findOrFail($item->product_id);
                $salidas = app(StockPorLote::class)->sacar($product, $traslado->origen_sede_id, $item->cantidad, $datos, 'quantity', verbo: 'trasladar');
                foreach ($salidas as $index => $salida) {
                    $fila = ['product_id' => $item->product_id, 'inventory_movement_id' => $salida->id, 'cantidad' => abs($salida->cantidad)];
                    if ($index === 0) {
                        $item->update($fila);
                    } else {
                        $traslado->items()->create($fila);
                    }
                }
            }
            $traslado->update(['estado' => InventoryTransfer::EN_TRANSITO]);
            AuditLogger::log('inventario.traslado_despachado', $traslado, ['estado' => $traslado->getOriginal('estado')], ['estado' => InventoryTransfer::EN_TRANSITO], userId: $user->id);

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

            abort_if($user->almacenRestringidoId() !== null && $user->almacenRestringidoId() !== $traslado->destino_sede_id, 403);

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
