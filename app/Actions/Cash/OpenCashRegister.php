<?php

namespace App\Actions\Cash;

use App\Models\CashRegister;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OpenCashRegister
{
    public function handle(User $vendedor, ?int $sedeId, float $montoApertura): CashRegister
    {
        // V7: la fila del trabajador queda bloqueada mientras se revisa y se
        // abre el turno; un doble clic espera y encuentra el turno ya abierto.
        return DB::transaction(function () use ($vendedor, $sedeId, $montoApertura) {
            User::query()->whereKey($vendedor->id)->lockForUpdate()->first();

            $hasOpenRegister = CashRegister::where('vendedor_id', $vendedor->id)
                ->where('estado', 'abierto')
                ->exists();

            if ($hasOpenRegister) {
                throw ValidationException::withMessages([
                    'cash_register' => 'Ya tienes un turno abierto',
                ]);
            }

            return CashRegister::create([
                'vendedor_id' => $vendedor->id,
                'sede_id' => $sedeId,
                'fecha_apertura' => now(),
                'monto_apertura' => $montoApertura,
                'estado' => 'abierto',
            ]);
        });
    }
}
