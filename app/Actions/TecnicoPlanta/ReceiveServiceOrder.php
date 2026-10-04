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
     *
     * @param  list<int>|null  $equiposRecibidos  los que llegaron (null = todos)
     */
    public function execute(
        ServiceOrder $serviceOrder,
        User $user,
        ?string $observaciones = null,
        int $equiposRecibidosCount = 0,
        ?string $diferencias = null,
        ?array $equiposRecibidos = null,
    ): ServiceOrder {
        // Se recibe una sola vez.
        if ($serviceOrder->estado !== 'pendiente_recepcion') {
            throw new InvalidArgumentException(sprintf(
                'No se puede recibir una orden en estado "%s".',
                $serviceOrder->estado
            ));
        }

        return DB::transaction(function () use ($serviceOrder, $user, $observaciones, $equiposRecibidosCount, $diferencias, $equiposRecibidos) {
            // Solo lo que llegó queda como recibido: lo que falta no se
            // certifica ni se le renuevan las fechas.
            $pivote = $serviceOrder->equipments()->newPivotStatement()->where('service_order_id', $serviceOrder->id);
            if ($equiposRecibidos === null) {
                $pivote->update(['recibido' => true, 'updated_at' => now()]);
            } else {
                (clone $pivote)->update(['recibido' => false, 'updated_at' => now()]);
                $pivote->whereIn('equipment_id', $equiposRecibidos)->update(['recibido' => true, 'updated_at' => now()]);
            }
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
