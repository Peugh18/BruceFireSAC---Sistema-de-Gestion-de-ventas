<?php

namespace App\Services\Certificates;

use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Service;
use App\Models\ServiceOrder;

class TipoAtencionExtintor
{
    /**
     * Atención real de los equipos seleccionados de la venta. Una unidad
     * nueva no es una recarga y una línea de servicio no basta para asumirla.
     * Los grupos mixtos conservan cada atención sin repetirla, en el orden
     * nuevo / recarga / mantenimiento. Sin evidencia devuelve una cadena vacía.
     *
     * @param  list<int>  $equipmentIds
     */
    public static function deVenta(Sale $sale, array $equipmentIds): string
    {
        $sale->loadMissing('items.service');

        $atenciones = $sale->items
            ->whereIn('equipment_id', $equipmentIds)
            ->map(fn (SaleItem $item) => $item->tipo_linea === 'unidad_nueva'
                ? 'nuevo'
                : self::deServicio($item->service))
            ->all();

        return implode(' / ', array_intersect(['nuevo', 'recarga', 'mantenimiento'], $atenciones));
    }

    /**
     * Atención del servicio contratado en la orden. No incorpora otros
     * servicios de la venta, que pueden corresponder a equipos distintos.
     * Sin evidencia de recarga o mantenimiento devuelve una cadena vacía.
     */
    public static function deOrden(ServiceOrder $order): string
    {
        $order->loadMissing('service');

        return self::deServicio($order->service);
    }

    /**
     * Recarga con mantenimiento sigue siendo recarga; el mantenimiento solo
     * se etiqueta como tal cuando no hay evidencia de una recarga.
     */
    protected static function deServicio(?Service $service): string
    {
        if ($service === null) {
            return '';
        }

        if (in_array($service->categoria, ['recarga', 'recarga_mantenimiento'], true)
            || preg_match('/\brecargas?\b/iu', $service->nombre) === 1) {
            return 'recarga';
        }

        if ($service->categoria === 'mantenimiento'
            || preg_match('/\bmantenimiento\b/iu', $service->nombre) === 1) {
            return 'mantenimiento';
        }

        return '';
    }
}
