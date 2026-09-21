<?php

namespace App\Actions\Cash;

use App\Models\CashRegister;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class OpenCashRegister
{
    public function handle(User $vendedor, ?int $sedeId, float $montoApertura): CashRegister
    {
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
    }
}
