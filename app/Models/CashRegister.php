<?php

namespace App\Models;

use Database\Factories\CashRegisterFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $vendedor_id
 * @property int|null $sede_id
 * @property Carbon $fecha_apertura
 * @property float $monto_apertura
 * @property Carbon|null $fecha_cierre
 * @property float|null $monto_contado_cierre
 * @property float|null $monto_esperado_calculado
 * @property float|null $diferencia
 * @property string|null $observacion
 * @property string $estado
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $vendedor
 * @property-read Sede|null $sede
 */
#[Fillable([
    'vendedor_id',
    'sede_id',
    'fecha_apertura',
    'monto_apertura',
    'fecha_cierre',
    'monto_contado_cierre',
    'monto_esperado_calculado',
    'diferencia',
    'observacion',
    'estado',
])]
class CashRegister extends Model
{
    /** @use HasFactory<CashRegisterFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'fecha_apertura' => 'datetime',
            'fecha_cierre' => 'datetime',
            'monto_apertura' => 'decimal:2',
            'monto_contado_cierre' => 'decimal:2',
            'monto_esperado_calculado' => 'decimal:2',
            'diferencia' => 'decimal:2',
        ];
    }

    public function vendedor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vendedor_id');
    }

    public function sede(): BelongsTo
    {
        return $this->belongsTo(Sede::class);
    }

    public function estaAbierto(): bool
    {
        return $this->estado === 'abierto';
    }
}
