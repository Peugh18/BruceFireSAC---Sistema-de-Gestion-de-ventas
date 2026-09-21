<?php

namespace App\Services\Billing;

class DetraccionCalculator
{
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
