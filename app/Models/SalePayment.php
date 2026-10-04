<?php

namespace App\Models;

use Database\Factories\SalePaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $sale_id
 * @property int|null $installment_id
 * @property string $forma_pago
 * @property float $monto
 * @property string|null $numero_operacion
 * @property Carbon $fecha
 * @property int|null $user_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property int|null $anulado_por
 * @property string|null $anulado_motivo
 * @property-read Sale $sale
 * @property-read Installment|null $installment
 * @property-read User|null $user
 * @property-read User|null $anuladoPor
 */
#[Fillable(['sale_id', 'installment_id', 'forma_pago', 'monto', 'numero_operacion', 'fecha', 'user_id'])]
class SalePayment extends Model
{
    /** @use HasFactory<SalePaymentFactory> */
    use HasFactory;

    // Un cobro anulado queda tachado (deleted_at) y deja de contar en saldos
    // y caja, pero no se pierde.
    use SoftDeletes;

    protected static function booted(): void
    {
        static::creating(function (SalePayment $pago): void {
            $pago->user_id ??= auth()->id();
        });
    }

    /**
     * Anula el cobro: queda en el historial con quién y por qué.
     */
    public function anular(string $motivo, ?int $userId): void
    {
        $this->forceFill(['anulado_por' => $userId, 'anulado_motivo' => $motivo])->save();
        $this->delete();
    }

    protected function casts(): array
    {
        return [
            'monto' => 'decimal:2',
            'fecha' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Sale, $this>
     */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    /**
     * @return BelongsTo<Installment, $this>
     */
    public function installment(): BelongsTo
    {
        return $this->belongsTo(Installment::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function anuladoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'anulado_por');
    }
}
