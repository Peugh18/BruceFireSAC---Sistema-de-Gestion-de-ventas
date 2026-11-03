<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Un conteo del cajón al cerrar el turno (arqueo ciego, E-011).
 *
 * @property int $id
 * @property int $cash_register_id
 * @property int $user_id
 * @property float $monto
 * @property int|null $anulado_por_id
 * @property Carbon|null $anulado_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $anuladoPor
 * @property-read Collection<int, CashRegisterCountLine> $lineas
 */
#[Fillable(['cash_register_id', 'user_id', 'monto', 'anulado_por_id', 'anulado_at'])]
class CashRegisterCount extends Model
{
    /**
     * Billetes y monedas en céntimos, de mayor a menor. Van en céntimos porque
     * Laravel lee el punto de «0.10» como un nivel del arreglo al validar.
     */
    public const DENOMINACIONES = ['20000', '10000', '5000', '2000', '1000', '500', '200', '100', '50', '20', '10'];

    protected function casts(): array
    {
        return [
            'monto' => 'decimal:2',
            'anulado_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<CashRegisterCountLine, $this>
     */
    public function lineas(): HasMany
    {
        return $this->hasMany(CashRegisterCountLine::class)->orderByDesc('denominacion');
    }

    /**
     * @return BelongsTo<CashRegister, $this>
     */
    public function cashRegister(): BelongsTo
    {
        return $this->belongsTo(CashRegister::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function anuladoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'anulado_por_id');
    }
}
