<?php

namespace App\Models;

use Database\Factories\QuoteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $numero
 * @property int $vendedor_id
 * @property int $client_id
 * @property int|null $sede_id
 * @property int|null $vehicle_id
 * @property Carbon $fecha
 * @property Carbon $vigencia_hasta
 * @property string|null $condicion_pago_propuesta
 * @property float $subtotal
 * @property float $igv
 * @property float $total
 * @property string|null $observaciones
 * @property string $estado
 * @property Carbon|null $emitida_at
 * @property int|null $origen_alerta_equipment_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $vendedor
 * @property-read Client $client
 * @property-read Sede|null $sede
 * @property-read Vehicle|null $vehicle
 * @property-read Collection<int, QuoteItem> $items
 */
#[Fillable([
    'numero', 'vendedor_id', 'client_id', 'sede_id', 'vehicle_id', 'referencia', 'fecha', 'vigencia_hasta',
    'condicion_pago_propuesta', 'subtotal', 'igv', 'total', 'observaciones', 'estado',
    'emitida_at', 'origen_alerta_equipment_id',
])]
class Quote extends Model
{
    /** @use HasFactory<QuoteFactory> */
    use HasFactory;

    /**
     * Transiciones de estado válidas (Documento Maestro §12.1). Una cotización
     * no se mueve libremente entre estados: solo hacia adelante en este mapa.
     *
     * @var array<string, list<string>>
     */
    public const TRANSITIONS = [
        'borrador' => ['emitida', 'anulada'],
        'emitida' => ['enviada', 'vencida', 'anulada'],
        'enviada' => ['aceptada', 'rechazada', 'vencida', 'anulada'],
        'aceptada' => ['convertida'],
        'rechazada' => [],
        'vencida' => [],
        'convertida' => [],
        'anulada' => [],
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'vigencia_hasta' => 'date',
            'emitida_at' => 'datetime',
            'subtotal' => 'decimal:2',
            'igv' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function vendedor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vendedor_id');
    }

    /**
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return BelongsTo<Sede, $this>
     */
    public function sede(): BelongsTo
    {
        return $this->belongsTo(Sede::class);
    }

    /**
     * @return BelongsTo<Vehicle, $this>
     */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    /**
     * @return HasMany<QuoteItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(QuoteItem::class);
    }

    /** @return BelongsToMany<Equipment, $this> */
    public function equipments(): BelongsToMany
    {
        return $this->belongsToMany(Equipment::class);
    }

    /**
     * Venta en la que se convirtió la cotización.
     *
     * @return HasOne<Sale, $this>
     */
    public function sale(): HasOne
    {
        return $this->hasOne(Sale::class)->latestOfMany();
    }

    public function canTransitionTo(string $estado): bool
    {
        return in_array($estado, self::TRANSITIONS[$this->estado] ?? [], true);
    }

    public function tieneServicios(): bool
    {
        return $this->items->contains(fn (QuoteItem $item) => $item->esServicio());
    }
}
