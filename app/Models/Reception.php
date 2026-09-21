<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $proveedor
 * @property string|null $documento_referencia
 * @property Carbon $fecha
 * @property int $sede_almacen_id
 * @property int|null $user_id
 * @property string|null $observacion
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Sede $sedeAlmacen
 * @property-read User|null $user
 * @property-read Collection<int, ReceptionItem> $items
 * @property-read Collection<int, InventoryMovement> $movements
 */
#[Fillable(['proveedor', 'documento_referencia', 'fecha', 'sede_almacen_id', 'user_id', 'observacion'])]
class Reception extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
        ];
    }

    public function sedeAlmacen(): BelongsTo
    {
        return $this->belongsTo(Sede::class, 'sede_almacen_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ReceptionItem::class);
    }

    public function movements(): MorphMany
    {
        return $this->morphMany(InventoryMovement::class, 'referencia');
    }

    public function getTotalItemsAttribute(): int
    {
        return (int) $this->items->sum('cantidad');
    }

    public function getTotalConformeAttribute(): int
    {
        return (int) $this->items->sum('cantidad_conforme');
    }

    public function getTotalNoConformeAttribute(): int
    {
        return $this->total_items - $this->total_conforme;
    }
}
