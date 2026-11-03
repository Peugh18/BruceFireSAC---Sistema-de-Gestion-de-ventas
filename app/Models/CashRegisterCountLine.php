<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Cuántos billetes o monedas de una denominación había en el cajón.
 *
 * @property int $id
 * @property int $cash_register_count_id
 * @property float $denominacion
 * @property int $cantidad
 */
#[Fillable(['cash_register_count_id', 'denominacion', 'cantidad'])]
class CashRegisterCountLine extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'denominacion' => 'decimal:2',
            'cantidad' => 'integer',
        ];
    }
}
