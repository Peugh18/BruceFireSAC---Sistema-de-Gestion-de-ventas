<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Salida de efectivo de un turno de caja, con su motivo.
 *
 * @property int $id
 * @property int $cash_register_id
 * @property int $user_id
 * @property float $monto
 * @property string $motivo
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read CashRegister $cashRegister
 * @property-read User $user
 */
#[Fillable(['cash_register_id', 'user_id', 'monto', 'motivo'])]
class CashRegisterWithdrawal extends Model
{
    protected function casts(): array
    {
        return ['monto' => 'decimal:2'];
    }

    /** @return BelongsTo<CashRegister, $this> */
    public function cashRegister(): BelongsTo
    {
        return $this->belongsTo(CashRegister::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
