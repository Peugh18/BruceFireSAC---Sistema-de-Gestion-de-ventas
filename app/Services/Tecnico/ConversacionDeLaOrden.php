<?php

namespace App\Services\Tecnico;

use App\Models\Equipment;
use App\Models\Evidencia;
use App\Models\ServiceOrder;
use App\Models\ServiceOrderEvent;
use App\Models\User;

/**
 * La conversación de la orden es su línea de tiempo (ServiceOrderEvent):
 * los mensajes de las personas y los eventos del sistema, juntos y en orden.
 */
class ConversacionDeLaOrden
{
    /** @var array<string, string> */
    private const TITULOS = [
        'creada' => 'Orden creada',
        'recibida' => 'Recibida en el taller',
        'deficiencia_detectada' => 'Deficiencia detectada',
        'notificacion_vendedor' => 'Aviso para ventas',
        'autorizacion_registrada' => 'Autorización registrada',
        'trabajo_completado' => 'Trabajo completado',
        'entrega_registrada' => 'Entrega registrada',
        'otro' => 'Evento',
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
            ->map(fn (ServiceOrderEvent $evento) => $this->fila($evento))
            ->all();
    }

    /**
     * Lo que necesita la pantalla de la conversación.
     *
     * @return array{eventos: array<int, array<string, mixed>>, equipos: array<int, array{id: int, serie: string}>}
     */
    public function paraPagina(ServiceOrder $orden): array
    {
        return ['eventos' => $this->eventos($orden), 'equipos' => $this->equiposParaEtiquetar($orden)];
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
            'titulo' => $esMensaje ? 'Mensaje' : (is_string($accion) ? ucfirst(str_replace('_', ' ', $accion)) : (self::TITULOS[$evento->tipo] ?? 'Evento')),
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
