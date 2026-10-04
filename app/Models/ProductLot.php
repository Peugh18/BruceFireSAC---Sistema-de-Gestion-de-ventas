<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Lote de un producto en un almacén (guantes, filtros, mascarillas…), con su
 * fecha de vencimiento. Su saldo es la suma de sus movimientos de Kardex.
 *
 * @property int $id
 * @property int $product_id
 * @property int $sede_id
 * @property string $lote
 * @property Carbon|null $fecha_vencimiento
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Product $product
 * @property-read Sede $sede
 */
#[Fillable(['product_id', 'sede_id', 'lote', 'fecha_vencimiento'])]
class ProductLot extends Model
{
    /** Días antes del vencimiento en que el lote empieza a avisar. */
    public const DIAS_AVISO = 60;

    protected function casts(): array
    {
        return [
            'fecha_vencimiento' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsTo<Sede, $this>
     */
    public function sede(): BelongsTo
    {
        return $this->belongsTo(Sede::class);
    }

    /**
     * @return HasMany<InventoryMovement, $this>
     */
    public function movements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class, 'product_lot_id');
    }

    public function estaVencido(): bool
    {
        return $this->fecha_vencimiento !== null && $this->fecha_vencimiento->lt(today());
    }

    public function saldo(): int
    {
        return (int) $this->movements()->sum('cantidad');
    }
}
