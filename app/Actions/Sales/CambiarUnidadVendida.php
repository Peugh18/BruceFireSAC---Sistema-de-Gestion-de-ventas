<?php

namespace App\Actions\Sales;

use App\Actions\Certificates\IssueCertificate;
use App\Models\Certificate;
use App\Models\CertificateUnit;
use App\Models\InventoryMovement;
use App\Models\InventoryUnit;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Sede;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CambiarUnidadVendida
{
    public function __construct(
        protected IssueCertificate $issueCertificate,
    ) {}

    /**
     * Cambia el extintor entregado por otro del MISMO producto, antes o
     * después de enviar el comprobante a SUNAT y sin nota de crédito: el
     * comprobante no muestra la serie y el producto y el precio no cambian.
     * La unidad equivocada vuelve al stock, la correcta sale, el equipo del
     * cliente se corrige y sus certificados quedan con una revisión.
     */
    public function handle(Sale $sale, SaleItem $item, string $numeroSerieNueva, ?int $userId = null): SaleItem
    {
        if (! in_array($sale->estado, ['borrador', 'confirmada'], true) || $item->sale_id !== $sale->id) {
            throw ValidationException::withMessages([
                'numero_serie' => 'Solo se cambia un extintor de una venta vigente.',
            ]);
        }

        if ($item->tipo_linea !== 'unidad_nueva' || ! $item->inventory_unit_id) {
            throw ValidationException::withMessages([
                'numero_serie' => 'Esta línea no es un extintor con serie.',
            ]);
        }

        return DB::transaction(function () use ($sale, $item, $numeroSerieNueva, $userId) {
            $anterior = InventoryUnit::query()->lockForUpdate()->findOrFail($item->inventory_unit_id);
            $nueva = InventoryUnit::query()->lockForUpdate()->where('numero_serie', trim($numeroSerieNueva))->first();
            $almacenId = Sede::find($sale->sede_id)?->almacenEfectivoId() ?? (int) $sale->sede_id;

            $error = match (true) {
                ! $nueva => 'No existe ninguna unidad con esa serie.',
                $nueva->id === $anterior->id => 'Esa es la misma unidad que ya tiene la venta.',
                $nueva->product_id !== $anterior->product_id => 'La unidad nueva es de otro producto. Para cambiar el producto corrige la venta antes de enviarla a SUNAT o emite una nota de crédito.',
                ! $nueva->estaDisponible() => "Esa unidad no está disponible (estado: {$nueva->estado}).",
                $nueva->sede_almacen_id !== $almacenId => 'Esa unidad no está en el almacén de la sede de la venta.',
                default => null,
            };

            if ($error) {
                throw ValidationException::withMessages(['numero_serie' => $error]);
            }

            $motivo = "Serie {$anterior->numero_serie} cambiada por {$nueva->numero_serie}";

            $anterior->update(['estado' => 'disponible']);
            $this->movimiento($sale, $anterior, 'ingreso', "Devolución por cambio de extintor en venta {$sale->numero_interno}: {$motivo}", $userId);

            $nueva->update(['estado' => 'vendido']);
            $this->movimiento($sale, $nueva, 'salida_venta', "Venta {$sale->numero_interno} (cambio de extintor): {$motivo}", $userId);

            $item->update(['inventory_unit_id' => $nueva->id]);
            $item->equipment?->update([
                'numero_serie' => $nueva->numero_serie,
                'tipo_agente' => $nueva->agenteParaEquipo() ?? $item->equipment->tipo_agente,
                'capacidad' => $nueva->capacidad,
                'marca' => $nueva->marca,
                'serie_fabricante' => $nueva->serie_fabricante,
                'anio_fabricacion' => $nueva->anio_fabricacion,
            ]);

            if ($item->equipment_id) {
                $this->corregirCertificados($sale, $item->equipment_id, $motivo, $userId);
            }

            AuditLogger::log(
                action: 'venta.extintor_cambiado',
                entity: $sale,
                oldValues: ['numero_serie' => $anterior->numero_serie],
                newValues: ['numero_serie' => $nueva->numero_serie, 'motivo' => $motivo],
                userId: $userId,
            );

            return $item->refresh();
        });
    }

    protected function movimiento(Sale $sale, InventoryUnit $unit, string $tipo, string $observacion, ?int $userId): void
    {
        $movement = new InventoryMovement;
        $movement->inventory_unit_id = $unit->id;
        $movement->product_id = $unit->product_id;
        $movement->sede_id = $unit->sede_almacen_id;
        $movement->tipo = $tipo;
        // La unidad que sale resta y la que vuelve suma.
        $movement->cantidad = $tipo === 'salida_venta' ? -1 : 1;
        $movement->referencia_type = $sale->getMorphClass();
        $movement->referencia_id = $sale->id;
        $movement->user_id = $userId ?? $sale->vendedor_id;
        $movement->observacion = $observacion;
        $movement->save();
    }

    /**
     * Los certificados vigentes que incluyen ese equipo se rehacen con el
     * mismo número: la fila cambiada toma los datos de la unidad nueva y el
     * resto se conserva tal cual.
     */
    protected function corregirCertificados(Sale $sale, int $equipmentId, string $motivo, ?int $userId): void
    {
        Certificate::query()
            ->with('certificateUnits')
            ->where('sale_id', $sale->id)
            ->where('estado', 'vigente')
            ->whereHas('certificateUnits', fn ($query) => $query->where('equipment_id', $equipmentId))
            ->get()
            ->each(function (Certificate $certificate) use ($equipmentId, $motivo, $userId) {
                $unidades = $certificate->certificateUnits
                    ->sortBy('orden')
                    ->map(fn (CertificateUnit $unit) => [
                        'equipment_id' => $unit->equipment_id,
                        'numero_cliente' => $unit->numero_cliente,
                        'fecha_ultima_ph' => $unit->fecha_ultima_ph?->toDateString(),
                        'fecha_ultima_recarga' => $unit->fecha_ultima_recarga?->toDateString(),
                        'presion_ph' => $unit->presion_ph,
                        'tiempo_ph' => $unit->tiempo_ph,
                        'presion_trabajo' => $unit->presion_trabajo,
                        ...($unit->equipment_id === $equipmentId ? [] : [
                            'numero_serie' => $unit->numero_serie_snapshot,
                            'capacidad' => $unit->capacidad,
                            'marca' => $unit->marca,
                            'tipo_agente' => $unit->tipo_agente,
                            'anio_fabricacion' => $unit->anio_fabricacion,
                        ]),
                    ])
                    ->values()
                    ->all();

                $this->issueCertificate->corregir($certificate, $unidades, [], $motivo, $userId);
            });
    }
}
