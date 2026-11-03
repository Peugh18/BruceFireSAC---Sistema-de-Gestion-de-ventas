<?php

namespace App\Actions\Cash;

use App\Models\CashRegister;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * El Gerente deja que el vendedor vuelva a contar el cajón (E-011). El primer
 * conteo no se borra: queda anulado con quién lo autorizó, junto al nuevo.
 */
class AutorizarReconteoDeCaja
{
    public function handle(CashRegister $cashRegister, User $gerente): void
    {
        DB::transaction(function () use ($cashRegister, $gerente): void {
            $cashRegister = CashRegister::query()->lockForUpdate()->findOrFail($cashRegister->id);
            $conteo = $cashRegister->estaAbierto() ? $cashRegister->conteoVigente() : null;

            if (! $conteo) {
                throw ValidationException::withMessages([
                    'cash_register' => 'Este turno no tiene un conteo esperando explicación.',
                ]);
            }

            $conteo->update(['anulado_por_id' => $gerente->id, 'anulado_at' => now()]);

            AuditLogger::log(
                action: 'caja.reconteo_autorizado',
                entity: $cashRegister,
                newValues: ['conteo_anulado' => (float) $conteo->monto],
                userId: $gerente->id,
            );
        });
    }
}
