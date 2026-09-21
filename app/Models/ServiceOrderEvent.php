<?php

namespace App\Models;

use Database\Factories\ServiceOrderEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Evento append-only de comunicación de una orden. La tabla solo guarda
 * created_at porque no se editan mensajes históricos de la bitácora.
 *
 * @property int $id
 * @property int $service_order_id
 * @property string $tipo
 * @property int|null $user_id
 * @property array<string, mixed>|null $payload
 * @property Carbon|null $created_at
 * @property-read ServiceOrder $serviceOrder
 * @property-read User|null $user
 */
#[Fillable(['service_order_id', 'tipo', 'user_id', 'payload', 'created_at'])]
class ServiceOrderEvent extends Model
{
    /** @use HasFactory<ServiceOrderEventFactory> */
    use HasFactory;

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function serviceOrder(): BelongsTo
    {
        return $this->belongsTo(ServiceOrder::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
