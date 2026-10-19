<?php

namespace App\Services\Tecnico;

use App\Models\Equipment;
use App\Models\Evidencia;
use App\Models\ServiceOrder;
use App\Models\ServiceOrderEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * La conversación de la orden es su línea de tiempo (ServiceOrderEvent):
 * los mensajes de las personas y los eventos del sistema, juntos y en orden.
 */
class ConversacionDeLaOrden
{
    /** @var array<string, string> */
    private const TITULOS = [
        'alta_tecnica_rapida' => 'Alta técnica rápida',
        'recepcion_planta' => 'Recepción en planta',
        'creada' => 'Orden creada',
        'recibida' => 'Recibida en el taller',
        'deficiencia_detectada' => 'Deficiencia detectada',
        'notificacion_vendedor' => 'Aviso para ventas',
        'autorizacion_registrada' => 'Autorización registrada',
        'trabajo_completado' => 'Trabajo completado',
        'entrega_registrada' => 'Entrega registrada',
        'otro' => 'Evento',
        'deficiencias_requieren_autorizacion' => 'Hallazgo: espera la respuesta del cliente',
        'deficiencia_resuelta' => 'Hallazgo reparado',
        'consumo_repuesto_kardex' => 'Repuesto instalado',
        'adicional_cotizado' => 'Adicional cotizado',
        'entrega_asignada' => 'Entrega asignada a campo',
        'entrega_final_realizada' => 'Entregado al cliente',
        'orden_cerrada' => 'Orden cerrada',
        'orden_anulada' => 'Orden anulada',
    ];

    /**
     * Avisos del sistema que no van al chat: se repiten por cada extintor o
     * no cambian nada para quien conversa. Siguen en la bitácora.
     *
     * @var list<string>
     */
    private const RUIDO = [
        'alta_tecnica_rapida', 'tecnico_asignado', 'orden_tomada', 'orden_editada',
        'extintor_quitado', 'extintor_corregido', 'equipo_incluido_inspeccion',
        'cambio_estado_planta', 'checklist_tecnico_completado',
    ];

    /**
     * El origen con el que se firma un mensaje, según el rol de quien escribe.
     */
    public static function origenDe(User $user): string
    {
        return match (true) {
            $user->hasRole('Gerente') => 'gerente',
            $user->hasRole('TecnicoPlanta') => 'planta',
            $user->hasRole('TecnicoCampo') => 'campo',
            default => 'vendedor',
        };
    }

    /**
     * Quién puede leer y escribir en la orden: el Gerente, el vendedor de su
     * sede y el técnico que puede atenderla.
     */
    public static function puedeParticipar(User $user, ServiceOrder $orden): bool
    {
        if ($user->hasRole('Gerente')) {
            return true;
        }

        if ($user->hasRole('Vendedor')) {
            return ServiceOrder::query()->visiblePara($user)->whereKey($orden->id)->exists();
        }

        return ($user->hasRole('TecnicoPlanta') || $user->hasRole('TecnicoCampo')) && $orden->atendiblePor($user);
    }

    /**
     * @return array<int, array{id: int, titulo: string, mensaje: string|null, autor: string, origen: string, es_sistema: bool, equipo: string|null, fecha: string|null, adjuntos: array<int, array{id: int, tipo: string, nombre: string|null}>}>
     */
    public function eventos(ServiceOrder $orden): array
    {
        return $orden->events()
            ->with(['user:id,name', 'equipment:id,numero_serie', 'evidencias:id,service_order_event_id,tipo,nombre_original'])
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()
            ->reject(fn (ServiceOrderEvent $evento) => in_array($evento->payload['accion'] ?? null, self::RUIDO, true))
            ->map(fn (ServiceOrderEvent $evento) => $this->fila($evento))
            ->values()
            ->all();
    }

    /**
     * Mensajes de otras personas que el usuario aún no leyó, por orden.
     *
     * @param  iterable<int>  $ordenes
     * @return array<int, int> id de la orden => mensajes sin leer
     */
    public function noLeidos(User $usuario, iterable $ordenes): array
    {
        $ids = collect($ordenes)->map(fn ($id) => (int) $id)->values()->all();

        if ($ids === []) {
            return [];
        }

        return DB::table('service_order_events as e')
            ->leftJoin('conversacion_lecturas as l', fn ($join) => $join
                ->on('l.service_order_id', '=', 'e.service_order_id')
                ->where('l.user_id', '=', $usuario->id))
            ->whereIn('e.service_order_id', $ids)
            ->whereNotNull('e.payload->origen')
            ->where(fn ($q) => $q->whereNull('e.user_id')->orWhere('e.user_id', '!=', $usuario->id))
            ->where(fn ($q) => $q->whereNull('l.leido_hasta')->orWhereColumn('e.created_at', '>', 'l.leido_hasta'))
            ->groupBy('e.service_order_id')
            ->selectRaw('e.service_order_id, count(*) as total')
            ->pluck('total', 'e.service_order_id')
            ->map(fn ($total) => (int) $total)
            ->all();
    }

    /** El usuario abrió el chat: todo lo que hay hasta ahora queda leído. */
    public function marcarLeida(User $usuario, ServiceOrder $orden): void
    {
        DB::table('conversacion_lecturas')->updateOrInsert(
            ['user_id' => $usuario->id, 'service_order_id' => $orden->id],
            ['leido_hasta' => now(), 'updated_at' => now(), 'created_at' => now()],
        );
    }

    /**
     * Lo que necesita la pantalla de la conversación, con los mensajes que
     * quien la abre todavía no leyó.
     *
     * @return array{eventos: array<int, array<string, mixed>>, equipos: array<int, array{id: int, serie: string}>, no_leidos: int}
     */
    public function paraPagina(ServiceOrder $orden): array
    {
        $usuario = request()->user();

        return [
            'eventos' => $this->eventos($orden),
            'equipos' => $this->equiposParaEtiquetar($orden),
            'no_leidos' => $usuario instanceof User ? ($this->noLeidos($usuario, [$orden->id])[$orden->id] ?? 0) : 0,
        ];
    }

    /**
     * @return array<int, array{id: int, serie: string}>
     */
    public function equiposParaEtiquetar(ServiceOrder $orden): array
    {
        return $orden->equipments()->get(['equipment.id', 'equipment.numero_serie'])
            ->map(fn (Equipment $equipo) => ['id' => $equipo->id, 'serie' => $equipo->numero_serie])
            ->all();
    }

    /**
     * @return array{id: int, titulo: string, mensaje: string|null, autor: string, origen: string, es_sistema: bool, equipo: string|null, fecha: string|null, adjuntos: array<int, array{id: int, tipo: string, nombre: string|null}>}
     */
    protected function fila(ServiceOrderEvent $evento): array
    {
        $payload = $evento->payload ?? [];
        $esMensaje = ($payload['accion'] ?? null) === 'mensaje' || isset($payload['origen']);
        $accion = $payload['accion'] ?? null;

        return [
            'id' => $evento->id,
            'titulo' => $esMensaje ? 'Mensaje' : (is_string($accion) ? (self::TITULOS[$accion] ?? ucfirst(str_replace('_', ' ', $accion))) : (self::TITULOS[$evento->tipo] ?? 'Evento')),
            'mensaje' => isset($payload['mensaje']) ? (string) $payload['mensaje'] : (isset($payload['descripcion']) ? (string) $payload['descripcion'] : null),
            'autor' => $evento->user->name ?? 'Sistema',
            'origen' => (string) ($payload['origen'] ?? 'sistema'),
            'es_sistema' => ! $esMensaje,
            'equipo' => $evento->equipment?->numero_serie,
            'fecha' => $evento->created_at?->toIso8601String(),
            'adjuntos' => $evento->evidencias->map(fn (Evidencia $e) => [
                'id' => $e->id,
                'tipo' => $e->tipo,
                'nombre' => $e->nombre_original,
            ])->values()->all(),
        ];
    }
}
