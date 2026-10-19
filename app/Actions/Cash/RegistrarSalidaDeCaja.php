<?php

namespace App\Actions\Cash;

use App\Models\CashRegister;
use App\Models\CashRegisterWithdrawal;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Saca efectivo de la caja abierta (un gasto, un pago menor) con su motivo.
 * No se puede sacar más de lo que hay en efectivo.
 */
class RegistrarSalidaDeCaja
{
    public function handle(CashRegister $cashRegister, User $user, float $monto, string $motivo): CashRegisterWithdrawal
    {
        return DB::transaction(function () use ($cashRegister, $user, $monto, $motivo): CashRegisterWithdrawal {
            $cashRegister = CashRegister::query()->lockForUpdate()->findOrFail($cashRegister->id);

            if (! $cashRegister->estaAbierto()) {
                throw ValidationException::withMessages(['monto' => 'La caja ya está cerrada.']);
            }

            $disponible = $cashRegister->efectivoEsperado();
            if (round($monto, 2) > $disponible) {
                throw ValidationException::withMessages([
                    'monto' => 'Solo hay S/ '.number_format($disponible, 2).' en efectivo.',
                ]);
            }

            $salida = $cashRegister->salidas()->create([
                'user_id' => $user->id,
                'monto' => round($monto, 2),
                'motivo' => $motivo,
            ]);

            AuditLogger::log(
                action: 'caja.salida',
                entity: $cashRegister,
                newValues: ['monto' => round($monto, 2), 'motivo' => $motivo],
                userId: $user->id,
            );

            return $salida;
        });
    }
}
