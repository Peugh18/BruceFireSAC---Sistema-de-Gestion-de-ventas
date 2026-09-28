<?php

namespace App\Models;

use App\Enums\DeficiencyCondition;
use Database\Factories\DeficiencyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $service_order_id
 * @property int|null $equipment_id
 * @property string $componente
 * @property string $condicion
 * @property string|null $foto_path
 * @property string|null $nota
 * @property string|null $accion_recomendada
 * @property string|null $repuesto_sugerido
 * @property bool $requiere_autorizacion
 * @property string $estado
 * @property string|null $resolucion
 * @property int|null $reported_by_user_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read ServiceOrder $serviceOrder
 * @property-read Equipment|null $equipment
 * @property-read DeficiencyAuthorization|null $authorization
 */
#[Fillable([
    'service_order_id',
    'equipment_id',
    'componente',
    'condicion',
    'foto_path',
    'nota',
    'accion_recomendada',
    'repuesto_sugerido',
    'requiere_autorizacion',
    'estado',
    'resolucion',
    'reported_by_user_id',
])]
class Deficiency extends Model
{
    /** @use HasFactory<DeficiencyFactory> */
    use HasFactory;

    /** @return Attribute<string, string> */
    protected function condicion(): Attribute
    {
        return Attribute::set(function (string $value): string {
            $condition = DeficiencyCondition::tryFrom($value);

            if ($condition !== null) {
                return $condition->value;
            }

            return DeficiencyCondition::fromDescription($value)->value;
        });
    }

    protected function casts(): array
    {
        return [
            'requiere_autorizacion' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<ServiceOrder, $this>
     */
    public function serviceOrder(): BelongsTo
    {
        return $this->belongsTo(ServiceOrder::class);
    }

    /**
     * @return BelongsTo<Equipment, $this>
     */
    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }

    /**
     * @return HasOne<DeficiencyAuthorization, $this>
     */
    public function authorization(): HasOne
    {
        return $this->hasOne(DeficiencyAuthorization::class);
    }
}
