<?php

namespace App\Models;

use Database\Factories\ServiceOrderLineFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['service_order_id', 'service_id', 'cantidad', 'quote_item_id', 'adicional'])]
class ServiceOrderLine extends Model
{
    /** @use HasFactory<ServiceOrderLineFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'cantidad' => 'integer',
            'adicional' => 'boolean',
        ];
    }

    /** @return BelongsTo<ServiceOrder, $this> */
    public function serviceOrder(): BelongsTo
    {
        return $this->belongsTo(ServiceOrder::class);
    }

    /** @return BelongsTo<Service, $this> */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /** @return BelongsTo<QuoteItem, $this> */
    public function quoteItem(): BelongsTo
    {
        return $this->belongsTo(QuoteItem::class);
    }

    /** @return BelongsToMany<Equipment, $this> */
    public function equipments(): BelongsToMany
    {
        return $this->belongsToMany(Equipment::class, 'service_order_equipment')
            ->wherePivot('service_order_id', $this->service_order_id)
            ->withPivot(['service_order_id', 'recibido', 'observaciones', 'posicion'])
            ->withTimestamps();
    }
}
