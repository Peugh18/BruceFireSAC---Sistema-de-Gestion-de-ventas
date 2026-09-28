<?php

namespace App\Actions\TecnicoPlanta;

use App\Models\ServiceOrder;
use App\Models\ServiceOrderEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ReceiveServiceOrder
{
    /**
     * Confirma la recepción de la orden en Planta (§18).
     * Transición: pendiente_recepcion -> recibido_planta.
     * Registra evento inmutable en ServiceOrderEvent con la bitácora de recepción y diferencias.
     */
    public function execute(
        ServiceOrder $serviceOrder,
        User $user,
        ?string $observaciones = null,
        int $equiposRecibidosCount = 0,
        ?string $diferencias = null
    ): ServiceOrder {
        if ($serviceOrder->estado !== 'recibido_planta' && $serviceOrder->estado !== 'pendiente_recepcion') {
            throw new InvalidArgumentException(sprintf(
                'No se puede recibir una orden en estado "%s".',
                $serviceOrder->estado
            ));
        }

        return DB::transaction(function () use ($serviceOrder, $user, $observaciones, $equiposRecibidosCount, $diferencias) {
            $serviceOrder->equipments()->newPivotStatement()->where('service_order_id', $serviceOrder->id)->update(['recibido' => true, 'updated_at' => now()]);
            $estadoAnterior = $serviceOrder->estado;

            $serviceOrder->update([
                'estado' => 'recibido_planta',
                'tecnico_id' => $serviceOrder->tecnico_id ?: $user->id,
                'departamento_tecnico' => 'planta',
                'observaciones' => $observaciones ?: $serviceOrder->observaciones,
            ]);

            // Registrar evento append-only de recepción
            ServiceOrderEvent::create([
                'service_order_id' => $serviceOrder->id,
                'tipo' => 'recibida',
                'user_id' => $user->id,
                'payload' => [
                    'accion' => 'recepcion_planta',
                    'estado_anterior' => $estadoAnterior,
                    'estado_nuevo' => 'recibido_planta',
                    'equipos_recibidos_count' => $equiposRecibidosCount,
                    'diferencias' => $diferencias,
                    'observaciones' => $observaciones,
                    'fecha' => now()->toIso8601String(),
                ],
            ]);

            return $serviceOrder->refresh();
        });
    }
}
