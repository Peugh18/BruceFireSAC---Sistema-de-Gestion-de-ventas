<?php

namespace App\Models;

use Database\Factories\ServiceOrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
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
 * @property int $service_id
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
    'codigo', 'client_id', 'sede_id', 'vehicle_id', 'referencia', 'quote_id', 'sale_id', 'service_id',
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
     * Terminadas en taller y aún sin entregar al cliente: con el certificado
     * emitido ya se pueden entregar en mostrador.
     *
     * @var list<string>
     */
    public const ESTADOS_LISTOS = ['listo_certificado', 'listo_entrega'];

    /**
     * Desde dónde se registra la entrega en mostrador (`entregado` viene de
     * campo, sin el acta del cliente todavía).
     *
     * @var list<string>
     */
    public const ESTADOS_PARA_ENTREGAR = ['listo_certificado', 'listo_entrega', 'entregado'];

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

    /**
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return BelongsTo<Sede, $this>
     */
    public function sede(): BelongsTo
    {
        return $this->belongsTo(Sede::class);
    }

    /**
     * @return BelongsTo<Vehicle, $this>
     */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    /**
     * @return BelongsTo<Quote, $this>
     */
    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function tecnico(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tecnico_id');
    }

    /**
     * @return HasMany<ServiceOrderEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(ServiceOrderEvent::class);
    }

    /**
     * @return BelongsTo<Sale, $this>
     */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    /** @return BelongsTo<Service, $this> */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /**
     * @return BelongsToMany<Equipment, $this>
     */
    public function equipments(): BelongsToMany
    {
        return $this->belongsToMany(Equipment::class, 'service_order_equipment')
            ->withPivot(['recibido', 'observaciones'])
            ->withTimestamps();
    }

    /**
     * @return HasMany<Deficiency, $this>
     */
    public function deficiencies(): HasMany
    {
        return $this->hasMany(Deficiency::class);
    }

    /**
     * @return HasMany<TechnicalChecklist, $this>
     */
    public function checklists(): HasMany
    {
        return $this->hasMany(TechnicalChecklist::class);
    }

    /**
     * @return HasMany<Certificate, $this>
     */
    public function certificates(): HasMany
    {
        return $this->hasMany(Certificate::class);
    }

    /** @param Builder<ServiceOrder> $query */
    public function scopeAccessibleToTechnician(Builder $query, User $user): void
    {
        $query->when($user->sede_id, fn (Builder $query) => $query->whereIn('sede_id', Sede::idsAtendidosPor((int) $user->sede_id)))
            ->where(fn (Builder $query) => $query->whereNull('tecnico_id')->orWhere('tecnico_id', $user->id));
    }

    /**
     * El técnico puede atender esta orden: es de su sede o de una tienda
     * que depende de su sede, y no está asignada a otro técnico.
     */
    public function atendiblePor(User $user): bool
    {
        $deSuSede = ! $user->sede_id || in_array((int) $user->sede_id, $this->sede?->idsQueAtienden() ?? [(int) $this->sede_id], true);

        return $deSuSede && ($this->tecnico_id === null || $this->tecnico_id === $user->id);
    }

    /**
     * Quién tiene la orden a su cargo, para que el técnico sepa si puede
     * tomarla.
     *
     * @return array{orden_id: int, area: string, tecnico: string|null, es_mia: bool}
     */
    public function asignacionPara(User $user): array
    {
        $this->loadMissing('tecnico:id,name');

        return [
            'orden_id' => $this->id,
            'area' => $this->departamento_tecnico === 'campo' ? 'campo' : 'planta',
            'tecnico' => $this->tecnico?->name,
            'es_mia' => $this->tecnico_id === $user->id,
        ];
    }

    public function coarseLabel(): string
    {
        return self::COARSE_LABELS[$this->estado] ?? 'asignada';
    }
}
