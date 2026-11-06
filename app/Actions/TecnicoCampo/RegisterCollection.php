<?php

namespace App\Actions\TecnicoCampo;

use App\Models\Service;
use App\Models\ServiceOrder;
use App\Models\ServiceOrderEvent;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class RegisterCollection
{
    /**
     * Registra el recojo físico de extintores en las instalaciones del cliente (§22.1)
     * y genera el primer eslabón de la Cadena de Custodia (§22.4, §85.6.3).
     *
     * @param  array{
     *     contacto_nombre: string,
     *     contacto_telefono?: string|null,
     *     observaciones?: string|null,
     *     conformidad_cliente: bool,
     *     foto_path?: string|null,
     *     equipos_ids?: list<int>|null,
     *     conteo?: list<array{tipo_agente: string, capacidad: string, cantidad: int|string, precio?: float|int|string|null}>
     * }  $data
     */
    public function execute(
        ServiceOrder $serviceOrder,
        User $user,
        array $data
    ): ServiceOrder {
        if (! $data['conformidad_cliente']) {
            throw new InvalidArgumentException('Se requiere la conformidad del cliente para el recojo de equipos.');
        }

        return DB::transaction(function () use ($serviceOrder, $user, $data) {
            // Bloqueada y releída (M6): el chequeo de estado y el guard de
            // "recojo ya registrado" valen DENTRO de la transacción; un doble
            // POST no puede duplicar el eslabón de custodia.
            $serviceOrder = ServiceOrder::query()->lockForUpdate()->findOrFail($serviceOrder->id);

            if ($serviceOrder->estado !== 'pendiente_recepcion' || $serviceOrder->events()->where('payload->eslabon_custodia', 'recojo_campo')->exists()) {
                throw new InvalidArgumentException('Este recojo ya se registró.');
            }

            // Lo que campo se llevó, por tipo y capacidad; sin conteo (orden con
            // sus extintores ya identificados), cuentan los de la orden.
            $conteo = array_map(fn (array $grupo) => [
                'tipo_agente' => $grupo['tipo_agente'],
                'capacidad' => trim($grupo['capacidad']),
                'cantidad' => (int) $grupo['cantidad'],
                'precio' => isset($grupo['precio']) && $grupo['precio'] !== '' ? round((float) $grupo['precio'], 2) : null,
            ], $data['conteo'] ?? []);
            $cantidad = $conteo !== [] ? array_sum(array_column($conteo, 'cantidad')) : $serviceOrder->equipments()->count();
            if ($cantidad === 0) {
                throw new InvalidArgumentException('Cuenta los extintores que te llevas antes de confirmar el recojo.');
            }
            $this->cobrarComoAcordo($serviceOrder, $conteo);
            $contactoNombre = trim($data['contacto_nombre']);
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
                    'conteo' => $conteo,
                    'observaciones' => $observaciones,
                    'conformidad_cliente' => true,
                    'foto_path' => $fotoPath,
                    'fecha_hora' => now()->toIso8601String(),
                ],
            ]);

            AuditLogger::log(
                action: 'orden.recojo_confirmado',
                entity: $serviceOrder,
                newValues: [
                    'contacto' => $contactoNombre,
                    'equipos_recogidos' => $cantidad,
                ],
                userId: $user->id,
            );

            return $serviceOrder->refresh();
        });
    }

    /**
     * Sin cotización, lo que cobra el vendedor es lo que campo dejó al cliente:
     * una línea por tipo y capacidad con su precio. Queda anotado si fue el de
     * lista, uno rebajado o uno sin precio de lista, para que el gerente lo vea.
     *
     * @param  array<int, array{tipo_agente: string, capacidad: string, cantidad: int, precio: float|null}>  $conteo
     */
    private function cobrarComoAcordo(ServiceOrder $orden, array $conteo): void
    {
        if ($orden->quote_id !== null || $conteo === [] || collect($conteo)->every(fn (array $g) => $g['precio'] === null)) {
            return;
        }

        $orden->lines()->where('adicional', false)->whereNull('quote_item_id')->delete();

        foreach ($conteo as $grupo) {
            $tarifa = Service::tarifaDeRecarga($grupo['tipo_agente'], $grupo['capacidad']);
            if ($grupo['precio'] === null || $grupo['precio'] <= 0) {
                throw new InvalidArgumentException("Pon el precio de {$grupo['tipo_agente']} {$grupo['capacidad']}.");
            }
            // Una capacidad que no estaba en el catálogo (30 lb, 50 kg) no frena el
            // recojo: se agrega su recarga, con su nombre real para la factura.
            $servicio = $tarifa['service_id'] ?? Service::crearRecarga($grupo['tipo_agente'], $grupo['capacidad'], $grupo['precio'])?->id;
            if ($servicio === null) {
                throw new InvalidArgumentException('No hay ningún servicio de recarga en el catálogo. Pide al gerente que lo agregue.');
            }

            $orden->lines()->create([
                'service_id' => $servicio,
                'cantidad' => $grupo['cantidad'],
                'precio_lista' => $tarifa['precio'] ?? null,
                'precio_unitario' => $grupo['precio'],
                'precio_estado' => match (true) {
                    $tarifa === null => 'sin_lista',
                    $grupo['precio'] < $tarifa['precio'] => 'rebajado',
                    default => 'lista',
                },
            ]);
        }
    }
}
