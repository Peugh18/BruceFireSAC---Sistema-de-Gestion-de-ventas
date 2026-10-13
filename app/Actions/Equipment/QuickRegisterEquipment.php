<?php

namespace App\Actions\Equipment;

use App\Enums\EquipmentType;
use App\Models\Equipment;
use App\Models\Service;
use App\Models\ServiceOrder;
use App\Models\ServiceOrderEvent;
use App\Models\ServiceOrderLine;
use App\Models\User;
use App\Services\Inventory\InventorySequenceGenerator;
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
            $this->ensureDefaultLine($serviceOrder);

            $search = trim((string) ($data['numero_serie'] ?? ''));
            $equipment = $search !== ''
                ? Equipment::query()
                    ->where(fn (Builder $query) => $query
                        ->where('numero_serie', $search)
                        ->orWhere('serie_fabricante', $search))
                    ->first()
                : null;

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
            $service = $line->service;
            $case = 'A_existente';

            if ($equipment) {
                $equipment->update(array_filter([
                    'tipo_agente' => $data['tipo_agente'] ?? $this->agentLabel($service),
                    'capacidad' => $data['capacidad'] ?? $this->capacityLabel($service),
                    'marca' => $data['marca'] ?? null,
                    'serie_fabricante' => $data['serie_fabricante'] ?? null,
                    'anio_fabricacion' => $data['anio_fabricacion'] ?? null,
                    'notas' => $data['notas'] ?? null,
                ], fn (mixed $value): bool => $value !== null && $value !== ''));
            } else {
                $case = 'B_nuevo';
                $equipment = Equipment::create([
                    'client_id' => $serviceOrder->client_id,
                    'numero_serie' => $this->sequenceGenerator->nextEquipmentSerial(),
                    'tipo_agente' => $this->valueOrUnreadable($data['tipo_agente'] ?? $this->agentLabel($service)),
                    'capacidad' => $this->valueOrUnreadable($data['capacidad'] ?? $this->capacityLabel($service)),
                    'marca' => $this->valueOrUnreadable($data['marca'] ?? null),
                    'serie_fabricante' => $this->valueOrUnreadable($data['serie_fabricante'] ?? null),
                    'anio_fabricacion' => $this->valueOrUnreadable($data['anio_fabricacion'] ?? null),
                    'ubicacion_actual' => 'Planta - Taller',
                    'estado' => 'en_servicio',
                    'fecha_venta' => now(),
                    'proxima_fecha_atencion' => now()->addYear(),
                    'proxima_prueba_hidrostatica' => now()->addYears(5),
                    'notas' => $data['notas'] ?? null,
                ]);
            }

            if (($incompatibilidad = $service->incompatibilidadConEquipo($equipment)) !== null) {
                throw ValidationException::withMessages(['tipo_agente' => $incompatibilidad]);
            }

            $position = ((int) $serviceOrder->equipments()->max('service_order_equipment.posicion')) + 1;
            $serviceOrder->equipments()->attach($equipment->id, [
                'service_order_line_id' => $line->id,
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
                    'service_order_line_id' => $line->id,
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
    private function resolveLine(ServiceOrder $serviceOrder, ?Equipment $equipment, array $data): ServiceOrderLine
    {
        $lineId = (int) ($data['service_order_line_id'] ?? 0);
        if ($lineId > 0) {
            return $serviceOrder->lines->firstWhere('id', $lineId)
                ?? throw ValidationException::withMessages(['service_order_line_id' => 'El casillero no pertenece a esta orden.']);
        }

        if ((bool) ($data['adicional'] ?? false)) {
            $service = Service::query()->find((int) ($data['service_id'] ?? 0));
            if (! $service) {
                throw ValidationException::withMessages(['service_id' => 'Elige el servicio del extintor adicional.']);
            }

            return $serviceOrder->lines()->create([
                'service_id' => $service->id,
                'cantidad' => 1,
                'adicional' => true,
            ])->load('service');
        }

        if (! $equipment) {
            throw ValidationException::withMessages(['service_order_line_id' => 'Elige el casillero donde irá el extintor.']);
        }

        $assignedByLine = DB::table('service_order_equipment')
            ->where('service_order_id', $serviceOrder->id)
            ->selectRaw('service_order_line_id, count(*) as total')
            ->groupBy('service_order_line_id')
            ->pluck('total', 'service_order_line_id');

        $line = $serviceOrder->lines
            ->first(fn (ServiceOrderLine $line): bool => $line->service
                && (int) ($assignedByLine[$line->id] ?? 0) < $line->cantidad
                && $line->service->incompatibilidadConEquipo($equipment) === null);

        if (! $line) {
            throw ValidationException::withMessages(['numero_serie' => 'No hay un casillero libre compatible para este extintor.']);
        }

        return $line;
    }

    private function agentLabel(Service $service): ?string
    {
        return (EquipmentType::tryFrom((string) $service->agente) ?? EquipmentType::fromDescription($service->nombre))->etiqueta();
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
}
