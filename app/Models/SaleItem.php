<?php

namespace App\Models;

use Database\Factories\SaleItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $sale_id
 * @property int $catalog_item_id
 * @property string $tipo_linea
 * @property int|null $inventory_unit_id
 * @property int|null $equipment_id
 * @property int $cantidad
 * @property float $precio_unitario
 * @property float $descuento
 * @property float $subtotal
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Sale $sale
 * @property-read CatalogItem $catalogItem
 * @property-read InventoryUnit|null $inventoryUnit
 * @property-read Equipment|null $equipment
 */
#[Fillable([
    'sale_id', 'catalog_item_id', 'tipo_linea', 'inventory_unit_id', 'equipment_id',
    'cantidad', 'precio_unitario', 'descuento', 'subtotal',
])]
class SaleItem extends Model
{
    /** @use HasFactory<SaleItemFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'precio_unitario' => 'decimal:2',
            'descuento' => 'decimal:2',
            'subtotal' => 'decimal:2',
        ];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function catalogItem(): BelongsTo
    {
        return $this->belongsTo(CatalogItem::class);
    }

    public function inventoryUnit(): BelongsTo
    {
        return $this->belongsTo(InventoryUnit::class);
    }

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }
}
