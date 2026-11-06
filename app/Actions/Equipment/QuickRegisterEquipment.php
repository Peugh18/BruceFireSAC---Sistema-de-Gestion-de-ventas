<?php

namespace App\Actions\Equipment;

use App\Enums\EquipmentType;
use App\Models\Equipment;
use App\Models\Service;
use App\Models\ServiceOrder;
use App\Models\ServiceOrderEvent;
use App\Models\ServiceOrderLine;
use App\Models\User;
use App\Services\Equipment\FechasDelEquipo;
use App\Services\Inventory\InventorySequenceGenerator;
use App\Services\ServiceOrders\CasillerosDeLaOrden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class QuickRegisterEquipment
{
    public const DEFAULT_UNREADABLE = 'No legible / Pendiente de verificar';

    public function __construct(protected InventorySequenceGenerator $sequenceGenerator) {}

    /** @param array<string, mixed> $data */
    public function execute(ServiceOrder $serviceOrder, array $data, ?User $user = null): Equipment
    {
        return DB::transaction(function () use ($serviceOrder, $data, $user): Equipment {
            $serviceOrder = ServiceOrder::query()
                ->with(['lines.service'])
                ->lockForUpdate()
                ->findOrFail($serviceOrder->id);
            if (! in_array($serviceOrder->estado, CasillerosDeLaOrden::ESTADOS_EDITABLES, true)) {
                throw ValidationException::withMessages(['equipo' => 'Esta orden ya no admite cambios de extintores.']);
            }
            $this->ensureDefaultLine($serviceOrder);

            $search = trim((string) ($data['numero_serie'] ?? $data['serie_fabricante'] ?? ''));
            $equipment = $search !== ''
                ? Equipment::query()
                    ->where(fn (Builder $query) => $query
                        ->where('numero_serie', $search)
                        ->orWhere('serie_fabricante', $search))
                    ->first()
                : null;

            // Un sticker BF-EQ que no existe es un error de lectura; cualquier otro
            // código no encontrado es la serie de fábrica de un extintor nuevo.
            if ($search !== '' && ! $equipment) {
                if (preg_match('/^BF-EQ-/i', $search) === 1) {
                    throw ValidationException::withMessages(['numero_serie' => "No encontramos el sticker {$search}. Revisa el código o regístralo a mano."]);
                }
                $data['serie_fabricante'] = ($data['serie_fabricante'] ?? null) ?: $search;
            }

            if ($equipment && $equipment->client_id !== $serviceOrder->client_id) {
                throw ValidationException::withMessages(['numero_serie' => 'El extintor pertenece a otro cliente.']);
            }

            $otraOrden = $equipment?->serviceOrders()
                ->whereKeyNot($serviceOrder->id)
                ->whereNotIn('estado', ['entregado', 'cerrado', 'anulada'])
                ->value('codigo');

            if ($otraOrden) {
                throw ValidationException::withMessages(['numero_serie' => "Este extintor sigue en la orden {$otraOrden}: entrégalo o anula esa orden primero."]);
            }

            if ($equipment && $serviceOrder->equipments()->whereKey($equipment->id)->exists()) {
                throw ValidationException::withMessages(['numero_serie' => 'Este extintor ya está en la orden.']);
            }

            $line = $this->resolveLine($serviceOrder, $equipment, $data);
            $service = $line?->service;
            $case = 'A_existente';
            $datosPendientes = (bool) ($data['datos_tecnicos_pendientes'] ?? false);
            $fechas = FechasDelEquipo::paraGuardar($data, $equipment);

            if ($equipment) {
                $equipment->update(array_filter([
                    'tipo_agente' => $data['tipo_agente'] ?? null,
                    'capacidad' => $data['capacidad'] ?? null,
                    'marca' => $data['marca'] ?? null,
                    'serie_fabricante' => $data['serie_fabricante'] ?? null,
                    'anio_fabricacion' => $data['anio_fabricacion'] ?? null,
                    'notas' => $data['notas'] ?? null,
                ], fn (mixed $value): bool => $value !== null && $value !== ''));
                $equipment->update($fechas);
            } else {
                $case = 'B_nuevo';
                $equipment = Equipment::create([
                    'client_id' => $serviceOrder->client_id,
                    'numero_serie' => $this->sequenceGenerator->nextEquipmentSerial(),
                    'tipo_agente' => $this->valueOrUnreadable($datosPendientes ? null : ($data['tipo_agente'] ?? ($service ? $this->agentLabel($service) : null))),
                    'capacidad' => $this->valueOrUnreadable($datosPendientes ? null : ($data['capacidad'] ?? ($service ? $this->capacityLabel($service) : null))),
                    'marca' => $this->valueOrUnreadable($data['marca'] ?? null),
                    'serie_fabricante' => $this->valueOrUnreadable($data['serie_fabricante'] ?? null),
                    'anio_fabricacion' => $this->valueOrUnreadable($data['anio_fabricacion'] ?? null),
                    'ubicacion_actual' => 'Planta - Taller',
                    'estado' => 'en_servicio',
                    'fecha_venta' => null,
                    'proxima_fecha_atencion' => null,
                    'proxima_prueba_hidrostatica' => null,
                    'notas' => $data['notas'] ?? null,
                    ...$fechas,
                ]);
            }

            if ($service !== null && ($incompatibilidad = $service->incompatibilidadConEquipo($equipment)) !== null) {
                // Lo contó campo: planta no se equivoca en silencio. Se avisa y, si
                // confirma lo que tiene en la mano, la orden se corrige.
                if ($serviceOrder->conteoDeCampo() === []) {
                    throw ValidationException::withMessages(['tipo_agente' => $incompatibilidad]);
                }
                if (! ($data['corregir_conteo'] ?? false)) {
                    throw ValidationException::withMessages(['corregir_conteo' => "Campo contó {$service->agente} {$service->capacidad} y tú registras {$equipment->tipo_agente} {$equipment->capacidad}. Si es así, confirma y se corrige la orden."]);
                }
                $line = $this->corregirConteo($serviceOrder, $line, $equipment, $user);
            }

            $position = ((int) $serviceOrder->equipments()->max('service_order_equipment.posicion')) + 1;
            $serviceOrder->equipments()->attach($equipment->id, [
                'service_order_line_id' => $line?->id,
                'posicion' => $position,
                'recibido' => (bool) ($data['recibido'] ?? false),
                'observaciones' => $data['observaciones_recepcion'] ?? null,
            ]);

            ServiceOrderEvent::create([
                'service_order_id' => $serviceOrder->id,
                'tipo' => 'otro',
                'user_id' => $user?->id,
                'payload' => [
                    'accion' => 'alta_tecnica_rapida',
                    'caso' => $case,
                    'equipment_id' => $equipment->id,
                    'numero_serie' => $equipment->numero_serie,
                    'service_order_line_id' => $line?->id,
                    'datos_tecnicos_pendientes' => $datosPendientes,
                ],
            ]);

            return $equipment;
        });
    }

    private function ensureDefaultLine(ServiceOrder $serviceOrder): void
    {
        if ($serviceOrder->lines->isNotEmpty() || $serviceOrder->service_id === null) {
            return;
        }

        $serviceOrder->lines()->create([
            'service_id' => $serviceOrder->service_id,
            'cantidad' => max(1, $serviceOrder->equipments()->count()),
            'adicional' => false,
        ]);
        $serviceOrder->load('lines.service');
    }

    /** @param array<string, mixed> $data */
    private function resolveLine(ServiceOrder $serviceOrder, ?Equipment $equipment, array $data): ?ServiceOrderLine
    {
        $lineId = (int) ($data['service_order_line_id'] ?? 0);
        if ($lineId > 0) {
            $line = $serviceOrder->lines->firstWhere('id', $lineId)
                ?? throw ValidationException::withMessages(['service_order_line_id' => 'El casillero no pertenece a esta orden.']);
            if ($serviceOrder->equipments()->wherePivot('service_order_line_id', $line->id)->count() >= $line->cantidad) {
                throw ValidationException::withMessages(['service_order_line_id' => 'Todos los casilleros de esta línea están ocupados.']);
            }

            return $line;
        }

        if ((bool) ($data['adicional'] ?? false)) {
            $service = Service::query()->find((int) ($data['service_id'] ?? 0));
            if (! $service && $serviceOrder->service_id !== null) {
                throw ValidationException::withMessages(['service_id' => 'Elige el servicio del extintor adicional.']);
            }

            if (! $service) {
                return null;
            }

            return $serviceOrder->lines()->create([
                'service_id' => $service->id,
                'cantidad' => 1,
                'adicional' => true,
            ])->load('service');
        }

        if ($serviceOrder->lines->isEmpty() && $serviceOrder->service_id === null) {
            return null;
        }

        $assignedByLine = DB::table('service_order_equipment')
            ->where('service_order_id', $serviceOrder->id)
            ->selectRaw('service_order_line_id, count(*) as total')
            ->groupBy('service_order_line_id')
            ->pluck('total', 'service_order_line_id');

        // Un extintor nuevo sin casillero elegido va al primero libre; uno ya
        // registrado, al primero libre que coincide en agente y capacidad.
        $line = $serviceOrder->lines
            ->first(fn (ServiceOrderLine $line): bool => $line->service
                && (int) ($assignedByLine[$line->id] ?? 0) < $line->cantidad
                && ($equipment === null || $line->service->incompatibilidadConEquipo($equipment) === null));

        if (! $line && $serviceOrder->lines->isEmpty() && $serviceOrder->service_id === null) {
            return null;
        }

        if (! $line) {
            throw ValidationException::withMessages(['numero_serie' => $equipment
                ? 'No hay un casillero libre para este extintor. Agrégalo como extintor no cotizado.'
                : 'Ya no quedan casilleros libres. Agrégalo como extintor no cotizado.']);
        }

        return $line;
    }

    private function agentLabel(Service $service): string
    {
        return EquipmentType::nombreDe($service->agente) ?? EquipmentType::fromDescription($service->nombre)->etiqueta();
    }

    private function capacityLabel(Service $service): ?string
    {
        return Service::capacidadDelTexto($service->capacidad ?: $service->nombre);
    }

    private function valueOrUnreadable(mixed $value): string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : self::DEFAULT_UNREADABLE;
    }

    /**
     * Planta confirma que el extintor no es lo que contó campo (CO2 10 lb y no
     * 15 lb): sale de su casillero y va a la recarga que de verdad le toca, con
     * su precio de lista. El vendedor ve el aviso antes de cobrar.
     */
    private function corregirConteo(ServiceOrder $orden, ServiceOrderLine $linea, Equipment $equipo, ?User $user): ServiceOrderLine
    {
        $tarifa = Service::tarifaDeRecarga((string) $equipo->tipo_agente, (string) $equipo->capacidad);
        $precio = $tarifa['precio'] ?? (float) ($linea->precio_unitario ?? 0);
        $servicio = $tarifa['service_id'] ?? Service::crearRecarga((string) $equipo->tipo_agente, (string) $equipo->capacidad, $precio)?->id;
        if ($servicio === null) {
            throw ValidationException::withMessages(['corregir_conteo' => 'No hay una recarga para ese extintor en el catálogo. Pide al gerente que la agregue.']);
        }

        $antes = trim(($linea->service->agente ?? '').' '.($linea->service->capacidad ?? ''));
        $linea->cantidad > 1 ? $linea->decrement('cantidad') : $linea->delete();

        $nueva = $orden->lines()->where('service_id', $servicio)->where('adicional', false)->first();
        if ($nueva !== null) {
            $nueva->increment('cantidad');
        } else {
            $nueva = $orden->lines()->create([
                'service_id' => $servicio,
                'cantidad' => 1,
                'precio_lista' => $tarifa['precio'] ?? null,
                'precio_unitario' => $precio,
                'precio_estado' => $tarifa === null ? 'sin_lista' : 'lista',
            ]);
        }

        ServiceOrderEvent::create([
            'service_order_id' => $orden->id,
            'tipo' => 'otro',
            'user_id' => $user?->id,
            'payload' => [
                'origen' => 'planta',
                'mensaje' => "Planta corrigió el conteo: un extintor era {$equipo->tipo_agente} {$equipo->capacidad}, no {$antes}. Revisa el precio antes de cobrar.",
            ],
        ]);

        return $nueva->refresh();
    }
}
