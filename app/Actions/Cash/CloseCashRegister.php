<?php

namespace App\Actions\Cash;

use App\Models\CashRegister;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CloseCashRegister
{
    public function handle(CashRegister $cashRegister, float $montoContadoCierre, ?string $observacion = null): CashRegister
    {
        // Bloqueada y releída dentro de la transacción (M3): un cobro que
        // llega mientras se cierra toma el mismo bloqueo al ligarse al turno y
        // no puede quedar en un turno cerrado sin entrar al arqueo.
        return DB::transaction(function () use ($cashRegister, $montoContadoCierre, $observacion): CashRegister {
            $cashRegister = CashRegister::query()->lockForUpdate()->findOrFail($cashRegister->id);

            if (! $cashRegister->estaAbierto()) {
                throw ValidationException::withMessages([
                    'cash_register' => 'El turno de caja no se encuentra abierto',
                ]);
            }

            $now = now();
            $montoEsperadoCalculado = $cashRegister->efectivoEsperado();
            $diferencia = round($montoContadoCierre - $montoEsperadoCalculado, 2);

            // Si falta o sobra dinero, el vendedor tiene que decir por qué.
            if ($diferencia !== 0.0 && blank($observacion)) {
                throw ValidationException::withMessages([
                    'observacion' => ($diferencia < 0 ? 'Faltan S/ ' : 'Sobran S/ ')
                        .number_format(abs($diferencia), 2).'. Explica qué pasó antes de cerrar.',
                ]);
            }

            $cashRegister->update([
                'fecha_cierre' => $now,
                'monto_contado_cierre' => $montoContadoCierre,
                'monto_esperado_calculado' => $montoEsperadoCalculado,
                'observacion' => $observacion,
                'estado' => 'cerrado',
            ]);

            if ($diferencia !== 0.0) {
                AuditLogger::log(
                    action: 'caja.cierre_con_diferencia',
                    entity: $cashRegister,
                    newValues: [
                        'esperado' => $montoEsperadoCalculado,
                        'contado' => $montoContadoCierre,
                        'diferencia' => $diferencia,
                        'motivo' => $observacion,
                    ],
                    userId: $cashRegister->vendedor_id,
                );
            }

            return $cashRegister->fresh();
        });
    }
}
