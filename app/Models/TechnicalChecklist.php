<?php

namespace App\Models;

use Database\Factories\TechnicalChecklistFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Checklist Técnico Digital (§19).
 * Motor unificado para inspección física tanto en Planta (Taller) como en Campo (Visitas).
 *
 * @property int $id
 * @property int $service_order_id
 * @property int $equipment_id
 * @property int|null $user_id
 * @property string $origen
 * @property string|null $tipo_equipo
 * @property array<int, array{elemento: string, estado: string, observacion?: string|null}> $items
 * @property string $resultado_general
 * @property string|null $observaciones
 * @property-read ServiceOrder $serviceOrder
 * @property-read Equipment $equipment
 * @property-read User|null $user
 */
#[Fillable([
    'service_order_id',
    'equipment_id',
    'user_id',
    'origen',
    'tipo_equipo',
    'items',
    'resultado_general',
    'observaciones',
])]
class TechnicalChecklist extends Model
{
    /** @use HasFactory<TechnicalChecklistFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'items' => 'array',
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
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
