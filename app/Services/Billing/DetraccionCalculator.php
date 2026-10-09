<?php

namespace App\Services\Billing;

use App\Models\Sale;
use App\Models\SaleItem;
use Illuminate\Validation\ValidationException;

class DetraccionCalculator
{
    /** @return array{aplica:bool,monto:float,codigo_bien:string} */
    public function paraVenta(Sale $sale, ?string $tipoComprobante = null): array
    {
        $sinDetraccion = ['aplica' => false, 'monto' => 0.0, 'codigo_bien' => ''];
        if (($tipoComprobante ?? $sale->comprobante_tipo) !== 'factura') {
            return $sinDetraccion;
        }

        if ($sale->estado !== 'borrador' && $sale->codigo_detraccion_snapshot !== null && $sale->monto_detraccion_snapshot !== null) {
            return [
                'aplica' => (float) $sale->monto_detraccion_snapshot > 0,
                'monto' => (float) $sale->monto_detraccion_snapshot,
                'codigo_bien' => $sale->codigo_detraccion_snapshot,
            ];
        }

        if ((float) $sale->total <= (float) config('billing.detraccion.monto_minimo')) {
            return $sinDetraccion;
        }

        $sale->loadMissing('items.service');
        $servicios = $sale->items->filter(fn (SaleItem $item) => $item->esServicio() && ($item->tipo_afectacion_igv ?? $item->service->tipo_afectacion_igv ?? '10') === '10');
        if ($servicios->isEmpty()) {
            return $sinDetraccion;
        }

        $codigos = $servicios->map(function (SaleItem $item): string {
            $codigo = $item->service?->codigoDetraccion();
            if (! in_array($codigo, ['020', '022', '037'], true)) {
                throw ValidationException::withMessages(['items' => 'Revisa la clasificación de detracción del servicio '.($item->service->nombre ?? 'sin catálogo').' en el catálogo antes de emitir esta factura.']);
            }

            return $codigo;
        })->unique()->values();

        if ($codigos->count() > 1 || $servicios->count() !== $sale->items->count()) {
            throw ValidationException::withMessages(['items' => 'Esta factura combina servicios con códigos distintos o conceptos no sujetos a detracción. Separa los conceptos por clasificación; este emisor admite un solo código de detracción por comprobante.']);
        }

        return $this->calcular((float) $sale->total, true, (string) $codigos->first());
    }

    /** @return array{aplica:bool,monto:float,codigo_bien:string} */
    public function calcular(float $total, bool $esServicio, ?string $codigoBien = null): array
    {
        $aplica = $esServicio && $total > (float) config('billing.detraccion.monto_minimo');

        return [
            'aplica' => $aplica,
            'monto' => $aplica ? round($total * (float) config('billing.detraccion.tasa'), 2) : 0.0,
            'codigo_bien' => $codigoBien ?? (string) config('billing.detraccion.codigo_bien'),
        ];
    }
}
