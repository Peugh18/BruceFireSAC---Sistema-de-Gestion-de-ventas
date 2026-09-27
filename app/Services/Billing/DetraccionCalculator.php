<?php

namespace App\Services\Billing;

use App\Models\Sale;
use App\Models\SaleItem;

class DetraccionCalculator
{
    /**
     * Detracción de una venta según el comprobante que se emite: solo la
     * factura de servicios sobre el mínimo (la boleta nunca lleva detracción).
     *
     * @return array{aplica:bool,monto:float,codigo_bien:string}
     */
    public function paraVenta(Sale $sale, ?string $tipoComprobante = null): array
    {
        if (($tipoComprobante ?? $sale->comprobante_tipo) !== 'factura') {
            return ['aplica' => false, 'monto' => 0.0, 'codigo_bien' => (string) config('billing.detraccion.codigo_bien')];
        }

        $sale->loadMissing('items');
        $esServicio = $sale->items->contains(fn (SaleItem $item) => $item->esServicio());

        return $this->calcular((float) $sale->total, $esServicio);
    }

    /**
     * @return array{aplica:bool,monto:float,codigo_bien:string}
     */
    public function calcular(float $total, bool $esServicio): array
    {
        $aplica = $esServicio && $total > config('billing.detraccion.monto_minimo');

        return [
            'aplica' => $aplica,
            'monto' => $aplica ? round($total * config('billing.detraccion.tasa'), 2) : 0.0,
            'codigo_bien' => config('billing.detraccion.codigo_bien'),
        ];
    }
}
