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
 * @property Carbon $fecha
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Sale $sale
 */
#[Fillable(['sale_id', 'forma_pago', 'monto', 'motivo', 'user_id', 'fecha'])]
class SaleRefund extends Model
{
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
