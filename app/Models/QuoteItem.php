<?php

namespace App\Models;

use Database\Factories\QuoteItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $quote_id
 * @property int|null $product_id
 * @property int|null $service_id
 * @property int $cantidad
 * @property float $precio_unitario
 * @property float $descuento
 * @property float $subtotal
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Quote $quote
 * @property-read Product|null $product
 * @property-read Service|null $service
 * @property-read Product|Service|null $item
 */
#[Fillable(['quote_id', 'product_id', 'service_id', 'cantidad', 'precio_unitario', 'descuento', 'subtotal'])]
class QuoteItem extends Model
{
    /** @use HasFactory<QuoteItemFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'precio_unitario' => 'decimal:2',
            'descuento' => 'decimal:2',
            'subtotal' => 'decimal:2',
        ];
    }

    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function getItemAttribute(): Product|Service|null
    {
        return $this->product ?? $this->service;
    }

    public function esServicio(): bool
    {
        return $this->service_id !== null;
    }
}
