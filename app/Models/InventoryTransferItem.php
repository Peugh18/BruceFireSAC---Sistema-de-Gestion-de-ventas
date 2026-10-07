<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $inventory_transfer_id
 * @property int $product_id
 * @property int|null $inventory_movement_id
 * @property int|null $inventory_unit_id
 * @property int $cantidad
 * @property-read Product $product
 * @property-read InventoryMovement|null $salida
 * @property-read InventoryUnit|null $unit
 */
#[Fillable(['inventory_transfer_id', 'product_id', 'inventory_movement_id', 'inventory_unit_id', 'cantidad'])]
class InventoryTransferItem extends Model
{
    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsTo<InventoryMovement, $this>
     */
    public function salida(): BelongsTo
    {
        return $this->belongsTo(InventoryMovement::class, 'inventory_movement_id');
    }

    /**
     * @return BelongsTo<InventoryUnit, $this>
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(InventoryUnit::class, 'inventory_unit_id');
    }
}
