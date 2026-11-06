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
use Illuminate\Validation\ValidationException;

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
 * @property int|null $service_id
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
 * @property-read Service|null $service
 * @property-read Collection<int, ServiceOrderLine> $lines
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
    /** Nombre que se muestra mientras el recojo de campo aún no tiene servicio. */
    public const RECOJO_SIN_SERVICIO = 'Recojo de extintores';

    /** @use HasFactory<ServiceOrderFactory> */
    use HasFactory;

    /**
     * Orden de los 13 estados finos (Documento Maestro §16.2).
     *
     * @var list<string>
     */
    public const ESTADOS = [
        'pendiente_recepcion', 'recibido_planta', 'esperando_autorizacion',
        'autorizado', 'en_proceso', 'trabajo_terminado', 'pendiente_datos', 'datos_completos',
        'listo_certificado', 'listo_entrega', 'entregado', 'cerrado', 'anulada',
    ];

    /**
     * Hasta dónde se puede anular la orden: con el certificado emitido ya no.
     *
     * @var list<string>
     */
    public const ESTADOS_ANULABLES = [
        'pendiente_recepcion', 'recibido_planta', 'esperando_autorizacion',
        'autorizado', 'en_proceso', 'trabajo_terminado', 'pendiente_datos', 'datos_completos',
    ];

    /**
     * Terminadas en taller y aún sin entregar al cliente: con el certificado
     * emitido ya se pueden entregar en mostrador.
     *
     * @var list<string>
     */
    public const ESTADOS_LISTOS = ['listo_certificado', 'listo_entrega'];

    /**
     * El taller ya terminó con la orden (o se anuló): no se le agregan
     * checklists ni deficiencias.
     *
     * @var list<string>
     */
    public const ESTADOS_CERRADOS_AL_TALLER = ['trabajo_terminado', 'pendiente_datos', 'datos_completos', 'listo_certificado', 'listo_entrega', 'entregado', 'cerrado', 'anulada'];

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
        'anulada' => 'anulada',
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
     * @return HasMany<Evidencia, $this>
     */
    public function evidencias(): HasMany
    {
        return $this->hasMany(Evidencia::class);
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
     * @return HasMany<ServiceOrderLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(ServiceOrderLine::class);
    }

    /**
     * Campo solo hace recojos, entregas e instalaciones: la inspección y el
     * mantenimiento se atienden en planta.
     */
    /**
     * Lo que campo se llevó, por tipo y capacidad ("PQS 6 kg × 2"), del último
     * recojo confirmado; planta lo compara con lo que identifica al recibir.
     *
     * @return list<array{tipo_agente: string, capacidad: string, cantidad: int}>
     */
    public function conteoDeCampo(): array
    {
        $evento = $this->relationLoaded('events')
            ? $this->events->where('payload.eslabon_custodia', 'recojo_campo')->sortByDesc('id')->first()
            : $this->events()->where('payload->eslabon_custodia', 'recojo_campo')->latest('id')->first();

        /** @var list<array{tipo_agente: string, capacidad: string, cantidad: int}> */
        return $evento->payload['conteo'] ?? [];
    }

    /**
     * Entrada (recojo) y salida (entrega) de la orden: quién, cuándo, dónde,
     * cuántos, fotos y firma. Lo ven el vendedor, el gerente y planta.
     *
     * @return array{entrada: array<string, mixed>|null, salida: array<string, mixed>|null}
     */
    public function entradaYSalida(): array
    {
        $eventos = $this->events()->whereIn('payload->eslabon_custodia', ['recojo_campo', 'entrega_campo'])->with('user:id,name')->latest('id')->get();
        $evidencias = $this->evidencias()->whereIn('etapa', ['recojo', 'entrega'])->get(['id', 'etapa', 'tipo']);
        $lugar = preg_replace('/^\s*SEDE\s*:\s*/iu', '', (string) $this->referencia) ?: null;
        $tramo = function (string $eslabon, string $etapa, string $persona, string $cantidad) use ($eventos, $evidencias, $lugar): ?array {
            $evento = $eventos->first(fn ($e) => ($e->payload['eslabon_custodia'] ?? null) === $eslabon);

            return $evento === null ? null : [
                'fecha' => $evento->created_at?->timezone('America/Lima')->format('d/m/Y H:i'),
                'tecnico' => $evento->payload['responsable_nombre'] ?? $evento->user?->name,
                'lugar' => $lugar,
                'persona' => $evento->payload[$persona] ?? null,
                'cantidad' => $evento->payload[$cantidad] ?? null,
                'fotos' => $evidencias->where('etapa', $etapa)->where('tipo', 'foto')->pluck('id')->values(),
                'firma' => $evidencias->where('etapa', $etapa)->where('tipo', 'firma')->last()?->id,
            ];
        };

        return [
            'entrada' => $tramo('recojo_campo', 'recojo', 'contacto_cliente', 'cantidad_equipos'),
            'salida' => $tramo('entrega_campo', 'entrega', 'receptor_nombre', 'equipos_entregados_count'),
        ];
    }

    /**
     * Precios que dejó campo y si esperan al gerente.
     *
     * @return list<array{id: int, servicio: string|null, cantidad: int, precio_lista: float|null, precio: float|null, estado: string|null}>
     */
    public function preciosDeCampo(): array
    {
        return array_values($this->lines()->with('service:id,nombre')->whereNotNull('precio_unitario')->get()
            ->map(fn (ServiceOrderLine $linea) => [
                'id' => $linea->id,
                'servicio' => $linea->service?->nombre,
                'cantidad' => $linea->cantidad,
                'precio_lista' => $linea->precio_lista !== null ? (float) $linea->precio_lista : null,
                'precio' => (float) $linea->precio_unitario,
                'estado' => $linea->precio_estado,
            ])->all());
    }

    public static function exigirQueCampoLoAtienda(?string $departamento, ?Service $servicio): void
    {
        if ($departamento === 'campo' && $servicio && preg_match('/inspecci|mantenim/iu', (string) $servicio->nombre) === 1) {
            throw ValidationException::withMessages([
                'departamento_tecnico' => "Campo solo hace recojos, entregas e instalaciones: «{$servicio->nombre}» lo atiende planta.",
            ]);
        }
    }

    /**
     * Extintores nuevos de la cotización que todavía no se venden: el
     * certificado de la orden los espera para salir con todos los del local.
     */
    public function extintoresNuevosPorVender(): int
    {
        if ($this->sale && $this->sale->estado === 'confirmada') {
            return 0;
        }

        $this->loadMissing('quote.items.product');

        return (int) ($this->quote?->items
            ->filter(fn (QuoteItem $item): bool => (bool) $item->product?->serializado)
            ->sum('cantidad') ?? 0);
    }

    /**
     * Los extintores que se certifican y a los que se les renuevan las
     * fechas: los que llegaron al taller y cuya reparación el cliente no
     * rechazó (un extintor sin reparar no sale como operativo).
     *
     * @return Collection<int, Equipment>
     */
    public function equiposAptos(): Collection
    {
        $rechazados = $this->deficiencies()->where('estado', 'rechazada')->whereNotNull('equipment_id')->pluck('equipment_id');

        return $this->equipments()
            ->wherePivot('recibido', true)
            ->whereNotIn('equipment.id', $rechazados)
            ->where('equipment.estado', '!=', 'baja')
            ->get();
    }

    /**
     * Al entregarse (en mostrador o por Campo), los extintores vuelven con el
     * cliente: dejan de figurar en el taller (E-030).
     */
    public function marcarEquiposEntregados(): void
    {
        Equipment::query()
            ->whereIn('id', $this->equipments()->pluck('equipment.id'))
            ->update(['ubicacion_actual' => 'Sede cliente']);
    }

    /**
     * @return BelongsToMany<Equipment, $this>
     */
    public function equipments(): BelongsToMany
    {
        return $this->belongsToMany(Equipment::class, 'service_order_equipment')
            ->withPivot(['service_order_line_id', 'recibido', 'observaciones', 'posicion'])
            ->orderByRaw('COALESCE(service_order_equipment.posicion, 2147483647)')
            ->orderBy('equipment.id')
            ->withTimestamps();
    }

    public function resumenServicios(): string
    {
        $this->loadMissing('service:id,nombre', 'lines.service:id,nombre');

        $principal = $this->service->nombre ?? $this->lines->first()->service->nombre ?? self::RECOJO_SIN_SERVICIO;
        $otros = $this->lines
            ->filter(fn (ServiceOrderLine $line): bool => $line->service_id !== $this->service_id)
            ->pluck('service_id')
            ->unique()
            ->count();

        return $otros > 0 ? "{$principal} + {$otros} más" : $principal;
    }

    public function cantidadEquiposEsperada(): int
    {
        $this->loadMissing('lines');
        $cantidadLineas = (int) $this->lines->sum('cantidad');
        $cantidadRecojo = (int) $this->events()
            ->where('tipo', 'creada')
            ->get(['payload'])
            ->max(fn (ServiceOrderEvent $event): int => (int) ($event->payload['cantidad_esperada'] ?? 0));

        return max($cantidadLineas, $cantidadRecojo, $this->equipments()->count());
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

    /** @return Collection<int, Certificate> */
    public function certificadosParaEntrega(): Collection
    {
        return Certificate::query()
            ->with('certificateType')
            ->where('client_id', $this->client_id)
            ->where(fn (Builder $query) => $query->where('service_order_id', $this->id)
                ->orWhereHas('ordenesReutilizadas', fn (Builder $orders) => $orders->where('service_orders.id', $this->id)))
            ->orderBy('id')->get();
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
     * @return array{orden_id: int, area: string, tecnico: string|null, es_mia: bool, finalizada: bool}
     */
    public function asignacionPara(User $user): array
    {
        $this->loadMissing('tecnico:id,name');

        return [
            'orden_id' => $this->id,
            'area' => $this->departamento_tecnico === 'campo' ? 'campo' : 'planta',
            'tecnico' => $this->tecnico?->name,
            'es_mia' => $this->tecnico_id === $user->id,
            'finalizada' => in_array($this->estado, ['entregado', 'cerrado', 'anulada'], true),
        ];
    }

    /**
     * El técnico que registra el trabajo se queda con la orden si nadie la
     * tenía: no hace falta tomarla aparte antes de terminar.
     */
    public function tomarSiLibre(User $user): void
    {
        if ($this->tecnico_id !== null) {
            return;
        }

        $this->update(['tecnico_id' => $user->id]);
        $this->events()->create(['tipo' => 'otro', 'user_id' => $user->id, 'payload' => ['accion' => 'orden_tomada', 'mensaje' => "Orden tomada por {$user->name}."]]);
    }

    public function coarseLabel(): string
    {
        return self::COARSE_LABELS[$this->estado] ?? 'asignada';
    }

    /**
     * Órdenes que el usuario puede ver: el Gerente, todas; los demás, las de
     * su sede.
     *
     * @param  Builder<ServiceOrder>  $query
     */
    public function scopeVisiblePara(Builder $query, User $user): void
    {
        $query->when($user->sedeRestringidaId(), fn (Builder $query, int $sedeId) => $query->where('sede_id', $sedeId));
    }
}
