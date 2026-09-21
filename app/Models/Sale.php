<?php

namespace App\Models;

use Database\Factories\SaleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $numero_interno
 * @property int|null $quote_id
 * @property int $client_id
 * @property int|null $sede_id
 * @property int|null $vehicle_id
 * @property int $vendedor_id
 * @property Carbon $fecha
 * @property string $destino
 * @property string $condicion_pago
 * @property string $comprobante_tipo
 * @property float $subtotal
 * @property float $igv
 * @property float $total
 * @property string $estado
 * @property string|null $observaciones
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Quote|null $quote
 * @property-read Client $client
 * @property-read Sede|null $sede
 * @property-read Vehicle|null $vehicle
 * @property-read User $vendedor
 * @property-read Collection<int, SaleItem> $items
 * @property-read Collection<int, SalePayment> $payments
 * @property-read Collection<int, Installment> $installments
 * @property-read Collection<int, ElectronicDocument> $electronicDocuments
 */
#[Fillable([
    'numero_interno', 'quote_id', 'client_id', 'sede_id', 'vehicle_id', 'vendedor_id', 'fecha',
    'destino', 'condicion_pago', 'comprobante_tipo', 'subtotal', 'igv', 'total', 'estado', 'observaciones',
])]
class Sale extends Model
{
    /** @use HasFactory<SaleFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'subtotal' => 'decimal:2',
            'igv' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function sede(): BelongsTo
    {
        return $this->belongsTo(Sede::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function vendedor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vendedor_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SalePayment::class);
    }

    public function installments(): HasMany
    {
        return $this->hasMany(Installment::class);
    }

    public function electronicDocuments(): HasMany
    {
        return $this->hasMany(ElectronicDocument::class);
    }
}
