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
 * @property string|null $observacion_item
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Reception $reception
 * @property-read Product $product
 */
#[Fillable(['reception_id', 'product_id', 'cantidad', 'cantidad_conforme', 'observacion_item'])]
class ReceptionItem extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'cantidad' => 'integer',
            'cantidad_conforme' => 'integer',
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
