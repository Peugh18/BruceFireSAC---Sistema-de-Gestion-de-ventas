<?php

namespace App\Http\Controllers\Vendedor;

use App\Http\Controllers\Controller;
use App\Models\ServiceOrder;
use App\Models\ServiceOrderEvent;
use App\Models\Team;
use App\Services\Tecnico\ConversacionDeLaOrden;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CommunicationController extends Controller
{
    /**
     * Etapas del tablero de seguimiento, en orden, con los estados de la
     * orden que agrupa cada una.
     *
     * @var array<string, array{titulo: string, estados: list<string>}>
     */
    public const ETAPAS = [
        'por_recibir' => ['titulo' => 'Por recibir', 'estados' => ['pendiente_recepcion']],
        'revision' => ['titulo' => 'En revisión', 'estados' => ['recibido_planta', 'en_revision']],
        'autorizacion' => ['titulo' => 'Por autorizar', 'estados' => ['esperando_autorizacion']],
        'proceso' => ['titulo' => 'En proceso', 'estados' => ['autorizado', 'en_proceso', 'trabajo_terminado', 'pendiente_datos', 'datos_completos']],
        'listo' => ['titulo' => 'Listo', 'estados' => ['listo_certificado', 'listo_entrega']],
        'entregado' => ['titulo' => 'Entregado', 'estados' => ['entregado', 'cerrado']],
    ];

    private const EVENTOS = [
        'creada' => 'Orden creada',
        'recibida' => 'Recibida en el taller',
        'deficiencia_detectada' => 'Deficiencia detectada',
        'notificacion_vendedor' => 'Aviso para ventas',
        'autorizacion_registrada' => 'Autorización registrada',
        'trabajo_completado' => 'Trabajo completado',
        'otro' => 'Nota',
    ];

    /**
     * Seguimiento del taller: las órdenes de la sede por etapa y, al elegir
     * una, su avance, la línea de tiempo y las notas para el técnico.
     */
    public function index(Team $current_team, Request $request): Response
    {
        $sedeId = $request->user()->sedeRestringidaId();

        $ordenes = ServiceOrder::query()
            ->with(['client:id,razon_social', 'tecnico:id,name'])
            ->withCount(['events'])
            ->when($sedeId, fn ($query) => $query->where('sede_id', $sedeId))
            ->where(fn ($query) => $query
                ->whereNotIn('estado', ['entregado', 'cerrado'])
                ->orWhere('updated_at', '>=', now()->subDays(15)))
            ->orderByDesc('updated_at')
            ->limit(200)
            ->get();

        $columnas = collect(self::ETAPAS)->map(fn (array $etapa, string $clave) => [
            'clave' => $clave,
            'titulo' => $etapa['titulo'],
            'ordenes' => $ordenes
                ->filter(fn (ServiceOrder $orden) => in_array($orden->estado, $etapa['estados'], true))
                ->map(fn (ServiceOrder $orden) => $this->tarjeta($orden))
                ->values(),
        ])->values();

        $seleccionada = $request->integer('orden')
            ? $ordenes->firstWhere('id', $request->integer('orden'))
            : null;

        return Inertia::render('vendedor/comunicacion/index', [
            'columnas' => $columnas,
            'seleccionada' => $seleccionada ? $this->detalle($seleccionada) : null,
        ]);
    }

    public function nota(Team $current_team, ServiceOrder $service_order, Request $request): RedirectResponse
    {
        $sedeId = $request->user()->sedeRestringidaId();
        abort_if($sedeId !== null && (int) $service_order->sede_id !== $sedeId, 404);

        $datos = $request->validate(['mensaje' => ['required', 'string', 'max:500']]);

        $service_order->events()->create([
            'tipo' => 'otro',
            'user_id' => $request->user()->id,
            'payload' => ['mensaje' => $datos['mensaje'], 'origen' => 'vendedor'],
        ]);
        $service_order->touch();

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    protected function tarjeta(ServiceOrder $orden): array
    {
        return [
            'id' => $orden->id,
            'codigo' => $orden->codigo,
            'cliente' => $orden->client->razon_social,
            'servicio' => $orden->service->nombre,
            'tecnico' => $orden->tecnico?->name,
            'area' => $orden->departamento_tecnico ?? 'planta',
            'prioridad' => $orden->prioridad,
            'dias' => (int) $orden->created_at->diffInDays(now()),
            'por_autorizar' => $orden->estado === 'esperando_autorizacion',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function detalle(ServiceOrder $orden): array
    {
        $orden->load(['events' => fn ($query) => $query->with('user:id,name')->orderBy('created_at')]);

        $etapaActual = collect(self::ETAPAS)
            ->keys()
            ->search(fn (string $clave) => in_array($orden->estado, self::ETAPAS[$clave]['estados'], true));

        return [
            ...$this->tarjeta($orden),
            'estado' => $orden->estado,
            'referencia' => $orden->referencia,
            'conversacion' => app(ConversacionDeLaOrden::class)->paraPagina($orden),
            'observaciones' => $orden->observaciones,
            'etapas' => collect(self::ETAPAS)->pluck('titulo')->values(),
            'etapa_actual' => $etapaActual === false ? 0 : $etapaActual,
            'eventos' => $orden->events->map(fn (ServiceOrderEvent $evento) => [
                'id' => $evento->id,
                'titulo' => ($evento->payload['origen'] ?? null) === 'vendedor'
                    ? 'Nota de ventas'
                    : (self::EVENTOS[$evento->tipo] ?? 'Evento'),
                'mensaje' => $evento->payload['mensaje'] ?? $evento->payload['descripcion'] ?? null,
                'autor' => $evento->user?->name ?? 'Sistema',
                'de_ventas' => ($evento->payload['origen'] ?? null) === 'vendedor',
                'fecha' => $evento->created_at?->toIso8601String(),
            ])->values(),
        ];
    }
}
