<?php

namespace App\Models;

use Database\Factories\ServiceOrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Orden de servicio (Documento Maestro §16). El estado fino de 13 pasos es la
 * fuente de verdad; coarseLabel() deriva la etiqueta simplificada de 4 puntos
 * que usa el mockup de Vendedor — nunca se guardan ambos por separado.
 *
 * @property int $id
 * @property string $codigo
 * @property int $client_id
 * @property int|null $sede_id
 * @property int|null $vehicle_id
 * @property int|null $quote_id
 * @property int|null $sale_id
 * @property string $tipo_servicio
 * @property Carbon $fecha
 * @property int|null $tecnico_id
 * @property string|null $departamento_tecnico
 * @property string $prioridad
 * @property string|null $observaciones
 * @property string $estado
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Client $client
 * @property-read Sede|null $sede
 * @property-read Vehicle|null $vehicle
 * @property-read Quote|null $quote
 * @property-read User|null $tecnico
 * @property-read Collection<int, ServiceOrderEvent> $events
 */
#[Fillable([
    'codigo', 'client_id', 'equipment_id', 'sede_id', 'vehicle_id', 'quote_id', 'sale_id', 'tipo_servicio',
    'fecha', 'tecnico_id', 'departamento_tecnico', 'prioridad', 'observaciones', 'estado',
])]
class ServiceOrder extends Model
{
    /** @use HasFactory<ServiceOrderFactory> */
    use HasFactory;

    /**
     * Orden de los 13 estados finos (Documento Maestro §16.2).
     *
     * @var list<string>
     */
    public const ESTADOS = [
        'pendiente_recepcion', 'recibido_planta', 'en_revision', 'esperando_autorizacion',
        'autorizado', 'en_proceso', 'trabajo_terminado', 'pendiente_datos', 'datos_completos',
        'listo_certificado', 'listo_entrega', 'entregado', 'cerrado',
    ];

    /**
     * Mapa de los 13 estados finos al indicador de 4 puntos del mockup de
     * Vendedor (Asignada / En proceso / Completada / Cerrada).
     *
     * @var array<string, string>
     */
    private const COARSE_LABELS = [
        'pendiente_recepcion' => 'asignada',
        'recibido_planta' => 'asignada',
        'en_revision' => 'asignada',
        'esperando_autorizacion' => 'asignada',
        'autorizado' => 'en_proceso',
        'en_proceso' => 'en_proceso',
        'trabajo_terminado' => 'en_proceso',
        'pendiente_datos' => 'en_proceso',
        'datos_completos' => 'en_proceso',
        'listo_certificado' => 'completada',
        'listo_entrega' => 'completada',
        'entregado' => 'completada',
        'cerrado' => 'cerrada',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function sede(): BelongsTo
    {
        return $this->belongsTo(Sede::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    public function tecnico(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tecnico_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(ServiceOrderEvent::class);
    }

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }

    public function equipments(): BelongsToMany
    {
        return $this->belongsToMany(Equipment::class, 'service_order_equipment')
            ->withPivot(['recibido', 'observaciones'])
            ->withTimestamps();
    }

    public function deficiencies(): HasMany
    {
        return $this->hasMany(Deficiency::class);
    }

    public function checklists(): HasMany
    {
        return $this->hasMany(TechnicalChecklist::class);
    }

    public function certificates(): HasMany
    {
        return $this->hasMany(Certificate::class);
    }

    public function coarseLabel(): string
    {
        return self::COARSE_LABELS[$this->estado] ?? 'asignada';
    }
}
