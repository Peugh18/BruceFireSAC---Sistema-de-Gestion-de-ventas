<?php

namespace App\Actions\TecnicoPlanta;

use App\Models\Equipment;
use App\Models\ServiceOrder;
use App\Models\ServiceOrderEvent;
use App\Models\User;
use App\Services\Inventory\InventorySequenceGenerator;
use Illuminate\Support\Facades\DB;

class QuickRegisterEquipment
{
    public const DEFAULT_UNREADABLE = 'No legible / Pendiente de verificar';

    public function __construct(
        protected InventorySequenceGenerator $sequenceGenerator
    ) {}

    /**
     * Alta Técnica Rápida (§18).
     *
     * Caso A: Escanear barcode existente de Bruce Fire -> vincular a orden.
     * Caso B: Extintor externo o nuevo -> generar BF-EQ-XXXXXX con secuencia real,
     *         guardar datos técnicos mínimos ("No legible / Pendiente de verificar" si no legible)
     *         y vincular a orden.
     *
     * @param  array<string, mixed>  $data
     */
    public function execute(ServiceOrder $serviceOrder, array $data, ?User $user = null): Equipment
    {
        return DB::transaction(function () use ($serviceOrder, $data, $user) {
            $numeroSerie = trim((string) ($data['numero_serie'] ?? ''));

            // Caso A: ya existe equipo con este código
            $existing = null;
            if ($numeroSerie !== '') {
                $existing = Equipment::where('numero_serie', $numeroSerie)->first();
            }

            if ($existing) {
                // Si se proporcionaron datos adicionales/actualizados, guardarlos
                $existing->update(array_filter([
                    'tipo_agente' => $data['tipo_agente'] ?? null,
                    'capacidad' => $data['capacidad'] ?? null,
                    'marca' => $data['marca'] ?? null,
                    'serie_fabricante' => $data['serie_fabricante'] ?? null,
                    'anio_fabricacion' => $data['anio_fabricacion'] ?? null,
                    'ubicacion_actual' => $data['ubicacion_actual'] ?? null,
                    'notas' => $data['notas'] ?? null,
                ], fn ($v) => $v !== null && $v !== ''));

                $equipment = $existing;
                $caso = 'A_existente';
            } else {
                // Caso B: Equipo nuevo o de otra empresa sin código Bruce Fire
                $generatedSerial = $numeroSerie !== '' ? $numeroSerie : $this->sequenceGenerator->nextEquipmentSerial();

                $equipment = Equipment::create([
                    'client_id' => $serviceOrder->client_id,
                    'numero_serie' => $generatedSerial,
                    'tipo_agente' => $this->sanitizeOrUnreadable($data['tipo_agente'] ?? null),
                    'capacidad' => $this->sanitizeOrUnreadable($data['capacidad'] ?? null),
                    'marca' => $this->sanitizeOrUnreadable($data['marca'] ?? null),
                    'serie_fabricante' => $this->sanitizeOrUnreadable($data['serie_fabricante'] ?? null),
                    'anio_fabricacion' => $this->sanitizeOrUnreadable($data['anio_fabricacion'] ?? null),
                    'ubicacion_actual' => $data['ubicacion_actual'] ?? 'Planta - Taller',
                    'estado' => 'en_servicio',
                    'fecha_venta' => now(),
                    'foto_general_path' => $data['foto_general_path'] ?? null,
                    'foto_placa_path' => $data['foto_placa_path'] ?? null,
                    'notas' => $data['notas'] ?? null,
                ]);

                $caso = 'B_nuevo';
            }

            // Vincular en pivot a la orden de servicio
            $serviceOrder->equipments()->syncWithoutDetaching([
                $equipment->id => [
                    'recibido' => true,
                    'observaciones' => $data['observaciones_recepcion'] ?? null,
                ],
            ]);

            // Asignar equipo primario si la orden aún no tiene uno
            if (! $serviceOrder->equipment_id) {
                $serviceOrder->update(['equipment_id' => $equipment->id]);
            }

            // Registrar evento append-only en la orden
            ServiceOrderEvent::create([
                'service_order_id' => $serviceOrder->id,
                'tipo' => 'otro',
                'user_id' => $user?->id,
                'payload' => [
                    'accion' => 'alta_tecnica_rapida',
                    'caso' => $caso,
                    'equipment_id' => $equipment->id,
                    'numero_serie' => $equipment->numero_serie,
                    'tipo_agente' => $equipment->tipo_agente,
                    'capacidad' => $equipment->capacidad,
                    'marca' => $equipment->marca,
                    'timestamp' => now()->toIso8601String(),
                ],
            ]);

            return $equipment;
        });
    }

    private function sanitizeOrUnreadable(?string $value): string
    {
        $v = trim((string) $value);

        return $v !== '' ? $v : self::DEFAULT_UNREADABLE;
    }
}
