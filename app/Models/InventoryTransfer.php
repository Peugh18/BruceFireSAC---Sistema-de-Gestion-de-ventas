<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * Traslado de stock entre sedes. Sale del origen al crearse y entra al
 * destino solo cuando el almacén destino confirma la llegada: mientras
 * tanto está "en tránsito" (la guía motivo 04 sustenta el viaje).
 *
 * @property int $id
 * @property int $origen_sede_id
 * @property int $destino_sede_id
 * @property int $user_id
 * @property string $estado
 * @property string|null $observacion
 * @property int|null $recibido_por
 * @property Carbon|null $recibido_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Sede $origen
 * @property-read Sede $destino
 * @property-read Collection<int, InventoryTransferItem> $items
 * @property-read DispatchGuide|null $guia
 */
#[Fillable(['origen_sede_id', 'destino_sede_id', 'user_id', 'estado', 'observacion', 'recibido_por', 'recibido_at'])]
class InventoryTransfer extends Model
{
    public const EN_TRANSITO = 'en_transito';

    public const RECIBIDO = 'recibido';

    protected function casts(): array
    {
        return ['recibido_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<Sede, $this>
     */
    public function origen(): BelongsTo
    {
        return $this->belongsTo(Sede::class, 'origen_sede_id');
    }

    /**
     * @return BelongsTo<Sede, $this>
     */
    public function destino(): BelongsTo
    {
        return $this->belongsTo(Sede::class, 'destino_sede_id');
    }

    /**
     * @return HasMany<InventoryTransferItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(InventoryTransferItem::class);
    }

    /**
     * @return HasOne<DispatchGuide, $this>
     */
    public function guia(): HasOne
    {
        return $this->hasOne(DispatchGuide::class);
    }

    public function estaEnTransito(): bool
    {
        return $this->estado === self::EN_TRANSITO;
    }
}
