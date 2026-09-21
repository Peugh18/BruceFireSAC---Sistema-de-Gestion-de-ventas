<?php

namespace App\Actions\Cash;

use App\Models\CashRegister;
use App\Models\SalePayment;
use Illuminate\Validation\ValidationException;

class CloseCashRegister
{
    public function handle(CashRegister $cashRegister, float $montoContadoCierre, ?string $observacion = null): CashRegister
    {
        if (! $cashRegister->estaAbierto()) {
            throw ValidationException::withMessages([
                'cash_register' => 'El turno de caja no se encuentra abierto',
            ]);
        }

        $now = now();

        $totalEfectivo = (float) SalePayment::query()
            ->where('forma_pago', 'efectivo')
            ->whereHas('sale', function ($query) use ($cashRegister) {
                $query->where('vendedor_id', $cashRegister->vendedor_id);
            })
            ->whereBetween('created_at', [$cashRegister->fecha_apertura, $now])
            ->sum('monto');

        $montoEsperadoCalculado = round((float) $cashRegister->monto_apertura + $totalEfectivo, 2);
        $diferencia = round($montoContadoCierre - $montoEsperadoCalculado, 2);

        $cashRegister->update([
            'fecha_cierre' => $now,
            'monto_contado_cierre' => $montoContadoCierre,
            'monto_esperado_calculado' => $montoEsperadoCalculado,
            'diferencia' => $diferencia,
            'observacion' => $observacion,
            'estado' => 'cerrado',
        ]);

        return $cashRegister->fresh();
    }
}
