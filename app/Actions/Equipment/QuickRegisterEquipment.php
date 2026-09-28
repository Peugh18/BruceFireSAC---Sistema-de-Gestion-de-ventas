<?php

namespace App\Actions\Equipment;

use App\Models\Equipment;
use App\Models\ServiceOrder;
use App\Models\ServiceOrderEvent;
use App\Models\User;
use App\Services\Inventory\InventorySequenceGenerator;
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
            $serial = trim((string) ($data['numero_serie'] ?? ''));
            $equipment = $serial !== '' ? Equipment::where('numero_serie', $serial)->first() : null;
            if ($equipment && $equipment->client_id !== $serviceOrder->client_id) {
                throw ValidationException::withMessages(['numero_serie' => 'El extintor pertenece a otro cliente.']);
            }
            $case = 'A_existente';
            if ($equipment) {
                $equipment->update(array_filter(['tipo_agente' => $data['tipo_agente'] ?? null, 'capacidad' => $data['capacidad'] ?? null, 'marca' => $data['marca'] ?? null, 'serie_fabricante' => $data['serie_fabricante'] ?? null, 'notas' => $data['notas'] ?? null], fn (mixed $value): bool => $value !== null && $value !== ''));
            } else {
                $case = 'B_nuevo';
                $equipment = Equipment::create(['client_id' => $serviceOrder->client_id, 'numero_serie' => $this->sequenceGenerator->nextEquipmentSerial(), 'tipo_agente' => $this->valueOrUnreadable($data['tipo_agente'] ?? null), 'capacidad' => $this->valueOrUnreadable($data['capacidad'] ?? null), 'marca' => $this->valueOrUnreadable($data['marca'] ?? null), 'serie_fabricante' => $this->valueOrUnreadable($data['serie_fabricante'] ?? null), 'anio_fabricacion' => $this->valueOrUnreadable($data['anio_fabricacion'] ?? null), 'ubicacion_actual' => 'Planta - Taller', 'estado' => 'en_servicio', 'fecha_venta' => now(), 'notas' => $data['notas'] ?? null]);
            }
            $alreadyAttached = $serviceOrder->equipments()->whereKey($equipment->id)->exists();
            $serviceOrder->equipments()->syncWithoutDetaching([$equipment->id => ['recibido' => (bool) ($data['recibido'] ?? false), 'observaciones' => $data['observaciones_recepcion'] ?? null]]);
            if (! $alreadyAttached) {
                ServiceOrderEvent::create(['service_order_id' => $serviceOrder->id, 'tipo' => 'otro', 'user_id' => $user?->id, 'payload' => ['accion' => 'alta_tecnica_rapida', 'caso' => $case, 'equipment_id' => $equipment->id, 'numero_serie' => $equipment->numero_serie]]);
            }

            return $equipment;
        });
    }

    private function valueOrUnreadable(mixed $value): string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : self::DEFAULT_UNREADABLE;
    }
}
