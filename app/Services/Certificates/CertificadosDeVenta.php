<?php

namespace App\Services\Certificates;

use App\Models\Certificate;
use App\Models\Equipment;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\ServiceOrder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Certificados emitidos de una venta, con su tipo y número de unidades
 * (antes vivía como método estático de SaleCertificateController y lo usaba
 * también SaleController).
 */
class CertificadosDeVenta
{
    /** @return Collection<int, ServiceOrder> */
    public static function ordenesTerminadas(Sale $sale): Collection
    {
        $sale->loadMissing('serviceOrders.lines.service', 'serviceOrders.service', 'serviceOrders.events', 'serviceOrders.equipments');

        return $sale->serviceOrders->filter(fn (ServiceOrder $order) => $order->client_id === $sale->client_id
            && $order->sede_id === $sale->sede_id
            && in_array($order->estado, ['listo_certificado', 'listo_entrega', 'entregado', 'cerrado'], true));
    }

    /** @return Collection<int, Equipment> */
    public static function equipos(Sale $sale): Collection
    {
        $sale->loadMissing('items.equipment.product');
        $terminadas = self::ordenesTerminadas($sale);
        $enTaller = $sale->serviceOrders->flatMap(fn (ServiceOrder $order) => $order->equipments->pluck('id'));
        $equipos = $sale->items->filter(fn (SaleItem $item) => $item->tipo_linea === 'unidad_nueva' || ! $enTaller->contains($item->equipment_id))->pluck('equipment')->filter();
        foreach ($terminadas as $order) {
            $equipos = $equipos->concat($order->equiposAptos()->load('product'));
        }

        return $equipos->filter(fn (Equipment $equipo) => $equipo->client_id === $sale->client_id && $equipo->estado !== 'baja')
            ->unique('id')->values();
    }

    /** @return Builder<Certificate> */
    public static function propios(Sale $sale): Builder
    {
        return Certificate::query()->where('client_id', $sale->client_id)
            ->where(fn (Builder $query) => $query->where('sale_id', $sale->id)
                ->orWhereIn('service_order_id', self::ordenesTerminadas($sale)->pluck('id')));
    }

    public static function fechaRecarga(Sale $sale, int $equipmentId): ?string
    {
        $order = self::ordenesTerminadas($sale)->first(fn (ServiceOrder $order) => $order->equipments->contains('id', $equipmentId));
        if ($order) {
            $fecha = $order->events->where('tipo', 'trabajo_completado')->sortByDesc('created_at')->first()?->created_at;

            return $fecha?->toDateString();
        }

        return $sale->fecha->toDateString();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function deLaVenta(Sale $sale, bool $incluirOrdenes = false): array
    {
        $certificados = Certificate::query()
            ->with('certificateType')
            ->withCount('certificateUnits')
            ->where('client_id', $sale->client_id)
            ->where(fn ($query) => $query
                ->where('sale_id', $sale->id)
                ->orWhereHas('ventasReutilizadas', fn ($venta) => $venta->where('sales.id', $sale->id))
                ->when($incluirOrdenes, fn ($query) => $query->orWhereHas('serviceOrder', fn ($order) => $order
                    ->where('sale_id', $sale->id)
                    ->where('client_id', $sale->client_id)
                    ->where('sede_id', $sale->sede_id))
                    ->orWhereHas('ordenesReutilizadas', fn ($order) => $order
                        ->where('sale_id', $sale->id)
                        ->where('client_id', $sale->client_id)
                        ->where('sede_id', $sale->sede_id))))
            ->orderBy('id')
            ->get();

        $listado = [];
        foreach ($certificados as $certificate) {
            $listado[] = [
                'id' => $certificate->id,
                'numero' => $certificate->numero,
                'tipo' => $certificate->certificateType->nombre,
                'tipo_codigo' => $certificate->certificateType->codigo,
                'revision' => $certificate->revision,
                'estado' => $certificate->estado,
                'referencia' => $certificate->referencia,
                'unidades' => $certificate->certificate_units_count,
            ];
        }

        return $listado;
    }
}
