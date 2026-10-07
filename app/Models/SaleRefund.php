<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Dinero devuelto al cliente por una venta anulada o rebajada.
 *
 * @property int $id
 * @property int $sale_id
 * @property string $forma_pago
 * @property float $monto
 * @property string $motivo
 * @property int|null $user_id
 * @property int|null $cash_register_id
 * @property Carbon $fecha
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Sale $sale
 */
#[Fillable(['sale_id', 'forma_pago', 'monto', 'motivo', 'user_id', 'fecha', 'cash_register_id'])]
class SaleRefund extends Model
{
    protected static function booted(): void
    {
        // V7: la devolución sale del turno abierto del vendedor de la venta.
        static::creating(function (SaleRefund $devolucion): void {
            if (! array_key_exists('cash_register_id', $devolucion->getAttributes())) {
                $devolucion->cash_register_id = CashRegister::abiertaDe((int) Sale::query()->whereKey($devolucion->sale_id)->value('vendedor_id'))?->id;
            }
        });
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
}
