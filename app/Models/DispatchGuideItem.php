<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $dispatch_guide_id
 * @property int|null $product_id
 * @property string|null $codigo
 * @property string $descripcion
 * @property string $unidad
 * @property string $cantidad
 * @property string $peso_kg
 * @property-read Product|null $product
 */
#[Fillable(['dispatch_guide_id', 'product_id', 'codigo', 'descripcion', 'unidad', 'cantidad', 'peso_kg'])]
class DispatchGuideItem extends Model
{
    protected function casts(): array
    {
        return ['cantidad' => 'decimal:3', 'peso_kg' => 'decimal:3'];
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
