<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $reception_id
 * @property int $product_id
 * @property int $cantidad
 * @property int $cantidad_conforme
 * @property string|null $costo_unitario
 * @property string|null $observacion_item
 * @property string|null $lote
 * @property Carbon|null $fecha_vencimiento
 * @property int|null $product_lot_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Reception $reception
 * @property-read Product $product
 */
#[Fillable(['reception_id', 'product_id', 'cantidad', 'cantidad_conforme', 'costo_unitario', 'observacion_item', 'lote', 'fecha_vencimiento', 'product_lot_id'])]
class ReceptionItem extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'cantidad' => 'integer',
            'cantidad_conforme' => 'integer',
            'costo_unitario' => 'decimal:4',
            'fecha_vencimiento' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Reception, $this>
     */
    public function reception(): BelongsTo
    {
        return $this->belongsTo(Reception::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
