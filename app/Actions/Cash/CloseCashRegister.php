<?php

namespace App\Actions\Cash;

use App\Models\CashRegister;
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

        // Efectivo cobrado en el turno menos el devuelto a clientes.
        $totalEfectivo = $cashRegister->movimientosPorFormaDePago()['efectivo'] ?? 0.0;

        $montoEsperadoCalculado = round((float) $cashRegister->monto_apertura + $totalEfectivo, 2);

        $cashRegister->update([
            'fecha_cierre' => $now,
            'monto_contado_cierre' => $montoContadoCierre,
            'monto_esperado_calculado' => $montoEsperadoCalculado,
            'observacion' => $observacion,
            'estado' => 'cerrado',
        ]);

        return $cashRegister->fresh();
    }
}
