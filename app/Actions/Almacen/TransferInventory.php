<?php

namespace App\Actions\Almacen;

use App\Models\InventoryMovement;
use App\Models\InventoryUnit;
use App\Models\Product;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TransferInventory
{
    /** @param array{destination_sede_id:int, serials?:list<string>, product_id?:int, quantity?:int, observation?:string|null} $data */
    public function handle(int $sourceSedeId, array $data, User $user): void
    {
        if ($sourceSedeId === $data['destination_sede_id']) {
            throw ValidationException::withMessages(['destination_sede_id' => 'La sede destino debe ser diferente al origen.']);
        }

        DB::transaction(function () use ($sourceSedeId, $data, $user): void {
            $serials = collect($data['serials'] ?? [])->filter()->values();
            if ($serials->isNotEmpty()) {
                $units = InventoryUnit::query()->whereIn('numero_serie', $serials)->lockForUpdate()->get();
                if ($units->count() !== $serials->count() || $units->contains(fn (InventoryUnit $unit): bool => $unit->sede_almacen_id !== $sourceSedeId || ! $unit->estaDisponible())) {
                    throw ValidationException::withMessages(['serials' => 'Todas las unidades deben estar disponibles en la sede origen.']);
                }
                foreach ($units as $unit) {
                    $this->recordPair($unit->product_id, $sourceSedeId, $data['destination_sede_id'], 1, $user, $data['observation'] ?? null, $unit);
                    $unit->update(['sede_almacen_id' => $data['destination_sede_id']]);
                }
            } else {
                $productId = $data['product_id'] ?? null;
                $quantity = $data['quantity'] ?? null;
                if ($productId === null || $quantity === null) {
                    throw ValidationException::withMessages(['product_id' => 'Selecciona un producto y una cantidad.']);
                }
                $product = Product::query()->where('serializado', false)->findOrFail($productId);
                $available = (int) InventoryMovement::query()->where('product_id', $product->id)->where('sede_id', $sourceSedeId)->sum('cantidad');
                if ($available < $quantity) {
                    throw ValidationException::withMessages(['quantity' => 'No hay stock suficiente en la sede origen.']);
                }
                $this->recordPair($product->id, $sourceSedeId, $data['destination_sede_id'], $quantity, $user, $data['observation'] ?? null);
            }

            AuditLogger::log('inventario.traslado', new InventoryMovement, ['sede_id' => $sourceSedeId], ['sede_id' => $data['destination_sede_id'], 'serials' => $serials->all()], userId: $user->id);
        });
    }

    private function recordPair(int $productId, int $sourceSedeId, int $destinationSedeId, int $quantity, User $user, ?string $observation, ?InventoryUnit $unit = null): void
    {
        foreach ([[$sourceSedeId, -$quantity], [$destinationSedeId, $quantity]] as [$sedeId, $amount]) {
            InventoryMovement::create(['inventory_unit_id' => $unit?->id, 'product_id' => $productId, 'sede_id' => $sedeId, 'tipo' => 'traslado', 'cantidad' => $amount, 'user_id' => $user->id, 'observacion' => trim("Traslado entre sedes. {$observation}")]);
        }
    }
}
