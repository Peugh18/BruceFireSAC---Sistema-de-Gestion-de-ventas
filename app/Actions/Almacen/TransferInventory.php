<?php

namespace App\Actions\Almacen;

use App\Models\InventoryMovement;
use App\Models\InventoryUnit;
use App\Models\Product;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Inventory\StockPorLote;
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
                $this->trasladarSinSerie($product, $sourceSedeId, (int) $data['destination_sede_id'], (int) $quantity, $user, $data['observation'] ?? null);
            }

            $salida = InventoryMovement::query()->where('tipo', 'traslado')->where('sede_id', $sourceSedeId)->where('user_id', $user->id)->latest('id')->first();
            AuditLogger::log('inventario.traslado', $salida ?? new InventoryMovement, ['sede_id' => $sourceSedeId], [
                'sede_id' => $data['destination_sede_id'],
                'serials' => $serials->all(),
                'product_id' => $data['product_id'] ?? null,
                'quantity' => $data['quantity'] ?? $serials->count(),
            ], userId: $user->id);
        });
    }

    /**
     * Sale del origen (con bloqueo, sin dejarlo en negativo; con lote, lo que
     * vence primero) y entra al destino en el mismo lote y vencimiento.
     */
    private function trasladarSinSerie(Product $product, int $sourceSedeId, int $destinationSedeId, int $quantity, User $user, ?string $observation): void
    {
        $stock = app(StockPorLote::class);
        $datos = ['tipo' => 'traslado', 'user_id' => $user->id, 'observacion' => trim("Traslado entre sedes. {$observation}")];

        foreach ($stock->sacar($product, $sourceSedeId, $quantity, $datos, 'quantity', verbo: 'trasladar') as $salida) {
            $stock->ingresarComo($salida, $destinationSedeId, $datos);
        }
    }

    private function recordPair(int $productId, int $sourceSedeId, int $destinationSedeId, int $quantity, User $user, ?string $observation, ?InventoryUnit $unit = null): void
    {
        foreach ([[$sourceSedeId, -$quantity], [$destinationSedeId, $quantity]] as [$sedeId, $amount]) {
            InventoryMovement::create(['inventory_unit_id' => $unit?->id, 'product_id' => $productId, 'sede_id' => $sedeId, 'tipo' => 'traslado', 'cantidad' => $amount, 'user_id' => $user->id, 'observacion' => trim("Traslado entre sedes. {$observation}")]);
        }
    }
}
