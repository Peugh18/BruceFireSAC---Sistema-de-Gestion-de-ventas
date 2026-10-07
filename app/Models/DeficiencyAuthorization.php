<?php

namespace App\Models;

use Database\Factories\DeficiencyAuthorizationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $deficiency_id
 * @property string $autorizado_por
 * @property string $canal
 * @property Carbon $fecha
 * @property string|null $observacion
 * @property string|null $evidencia_path
 * @property int|null $cotizacion_adicional_id
 * @property float|null $importe
 * @property int $vendedor_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Deficiency $deficiency
 * @property-read User $vendedor
 * @property-read Quote|null $cotizacionAdicional
 */
#[Fillable([
    'deficiency_id',
    'autorizado_por',
    'canal',
    'fecha',
    'observacion',
    'evidencia_path',
    'cotizacion_adicional_id',
    'importe',
    'vendedor_id',
])]
class DeficiencyAuthorization extends Model
{
    /** @use HasFactory<DeficiencyAuthorizationFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'importe' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Deficiency, $this>
     */
    public function deficiency(): BelongsTo
    {
        return $this->belongsTo(Deficiency::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function vendedor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vendedor_id');
    }

    /**
     * @return BelongsTo<Quote, $this>
     */
    public function cotizacionAdicional(): BelongsTo
    {
        return $this->belongsTo(Quote::class, 'cotizacion_adicional_id');
    }
}
