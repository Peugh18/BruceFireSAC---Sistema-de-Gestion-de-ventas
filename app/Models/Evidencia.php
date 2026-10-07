<?php

namespace App\Models;

use Database\Factories\EvidenciaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Foto, audio, archivo o firma de una orden (§33). Solo guarda created_at:
 * una evidencia no se edita.
 *
 * @property int $id
 * @property int $service_order_id
 * @property int|null $equipment_id
 * @property int|null $service_order_event_id
 * @property int|null $deficiency_id
 * @property int|null $user_id
 * @property string $tipo
 * @property string $etapa
 * @property string $path
 * @property string|null $nombre_original
 * @property string $mime
 * @property int $tamano
 * @property Carbon|null $created_at
 * @property-read ServiceOrder $serviceOrder
 * @property-read User|null $user
 */
#[Fillable([
    'service_order_id', 'equipment_id', 'service_order_event_id', 'deficiency_id', 'user_id',
    'tipo', 'etapa', 'path', 'nombre_original', 'mime', 'tamano', 'created_at',
])]
class Evidencia extends Model
{
    /** @use HasFactory<EvidenciaFactory> */
    use HasFactory;

    public const ETAPAS = [
        'recepcion', 'recojo', 'deficiencia', 'antes', 'despues', 'entrega',
        'instalacion', 'inspeccion', 'mantenimiento', 'conversacion',
    ];

    protected $table = 'evidencias';

    public $timestamps = false;

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    /** @return BelongsTo<ServiceOrder, $this> */
    public function serviceOrder(): BelongsTo
    {
        return $this->belongsTo(ServiceOrder::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
