<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * Kardex: un registro por cada entrada/salida/ajuste/traslado de stock,
 * ligado opcionalmente a la unidad serializada exacta que se movió.
 *
 * @property int $id
 * @property int|null $inventory_unit_id
 * @property int $catalog_item_id
 * @property int $sede_id
 * @property string $tipo
 * @property int $cantidad
 * @property string|null $referencia_type
 * @property int|null $referencia_id
 * @property int|null $user_id
 * @property string|null $observacion
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read InventoryUnit|null $inventoryUnit
 * @property-read CatalogItem $catalogItem
 * @property-read Sede $sede
 * @property-read User|null $user
 */
#[Fillable(['inventory_unit_id', 'catalog_item_id', 'sede_id', 'tipo', 'cantidad', 'user_id', 'observacion'])]
class InventoryMovement extends Model
{
    public function inventoryUnit(): BelongsTo
    {
        return $this->belongsTo(InventoryUnit::class);
    }

    public function catalogItem(): BelongsTo
    {
        return $this->belongsTo(CatalogItem::class);
    }

    public function sede(): BelongsTo
    {
        return $this->belongsTo(Sede::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function referencia(): MorphTo
    {
        return $this->morphTo();
    }
}
