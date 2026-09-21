<?php

namespace App\Models;

use Database\Factories\InstallmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $sale_id
 * @property int $numero_cuota
 * @property Carbon $fecha_vencimiento
 * @property float $monto
 * @property string $estado
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Sale $sale
 */
#[Fillable(['sale_id', 'numero_cuota', 'fecha_vencimiento', 'monto', 'estado'])]
class Installment extends Model
{
    /** @use HasFactory<InstallmentFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'fecha_vencimiento' => 'date',
            'monto' => 'decimal:2',
        ];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }
}
