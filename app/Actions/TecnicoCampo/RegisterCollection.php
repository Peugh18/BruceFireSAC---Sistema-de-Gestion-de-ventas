<?php

namespace App\Actions\TecnicoCampo;

use App\Models\ServiceOrder;
use App\Models\ServiceOrderEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class RegisterCollection
{
    /**
     * Registra el recojo físico de extintores en las instalaciones del cliente (§22.1)
     * y genera el primer eslabón de la Cadena de Custodia (§22.4, §85.6.3).
     *
     * @param  array{
     *     cantidad: int,
     *     contacto_nombre: string,
     *     contacto_telefono?: string|null,
     *     observaciones?: string|null,
     *     conformidad_cliente: bool,
     *     foto_path?: string|null,
     *     equipos_ids?: list<int>|null
     * }  $data
     */
    public function execute(
        ServiceOrder $serviceOrder,
        User $user,
        array $data
    ): ServiceOrder {
        if (! ($data['conformidad_cliente'] ?? false)) {
            throw new InvalidArgumentException('Se requiere la conformidad del cliente para el recojo de equipos.');
        }

        if ($serviceOrder->estado !== 'pendiente_recepcion' || $serviceOrder->events()->where('payload->eslabon_custodia', 'recojo_campo')->exists()) {
            throw new InvalidArgumentException('Este recojo ya se registró.');
        }

        return DB::transaction(function () use ($serviceOrder, $user, $data) {
            $cantidad = $serviceOrder->equipments()->count();
            if ($cantidad === 0) {
                throw new InvalidArgumentException('Registra al menos un extintor antes de confirmar el recojo.');
            }
            $contactoNombre = trim((string) ($data['contacto_nombre'] ?? ''));
            $contactoTelefono = $data['contacto_telefono'] ?? null;
            $observaciones = $data['observaciones'] ?? null;
            $fotoPath = $data['foto_path'] ?? null;

            // Vincular equipos si se especificaron
            if (! empty($data['equipos_ids'])) {
                $serviceOrder->equipments()->syncWithoutDetaching($data['equipos_ids']);
            }

            // La orden sigue en pendiente_recepcion, ahora rumbo al taller:
            // pasa a Planta y sin técnico, para que el de planta la reciba.
            $serviceOrder->update([
                'departamento_tecnico' => 'planta',
                'tecnico_id' => null,
                'observaciones' => $observaciones ? ($serviceOrder->observaciones ? "{$serviceOrder->observaciones}\n[Recojo]: {$observaciones}" : "[Recojo]: {$observaciones}") : $serviceOrder->observaciones,
            ]);

            // Cadena de Custodia (§22.4, §85.6.3): Eslabón 1 -> Recogido por
            ServiceOrderEvent::create([
                'service_order_id' => $serviceOrder->id,
                'tipo' => 'otro',
                'user_id' => $user->id,
                'payload' => [
                    'eslabon_custodia' => 'recojo_campo',
                    'etapa' => 'Recogido por Técnico de Campo',
                    'responsable_nombre' => $user->name,
                    'contacto_cliente' => $contactoNombre,
                    'contacto_telefono' => $contactoTelefono,
                    'cantidad_equipos' => $cantidad,
                    'observaciones' => $observaciones,
                    'conformidad_cliente' => true,
                    'foto_path' => $fotoPath,
                    'fecha_hora' => now()->toIso8601String(),
                ],
            ]);

            return $serviceOrder->refresh();
        });
    }
}
