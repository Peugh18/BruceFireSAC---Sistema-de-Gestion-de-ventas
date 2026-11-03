<?php

namespace App\Actions\Cash;

use App\Models\CashRegister;
use App\Models\CashRegisterCount;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Arqueo ciego (E-011). El primer conteo queda guardado y no se cambia: si no
 * cuadra, el vendedor solo puede explicar qué pasó, sin saber cuánto falta o
 * sobra. Un nuevo conteo solo es posible si el Gerente autoriza el reconteo.
 */
class CloseCashRegister
{
    /**
     * @param  array<string, int>|null  $denominaciones  cantidad por billete o moneda, con la denominación en céntimos; si viene, el monto es su suma
     */
    public function handle(CashRegister $cashRegister, float $montoContadoCierre, ?string $observacion = null, ?array $denominaciones = null): CashRegister
    {
        $faltaExplicar = false;

        // Bloqueada y releída dentro de la transacción (M3): un cobro que
        // llega mientras se cierra toma el mismo bloqueo al ligarse al turno y
        // no puede quedar en un turno cerrado sin entrar al arqueo.
        $resultado = DB::transaction(function () use ($cashRegister, $montoContadoCierre, $observacion, $denominaciones, &$faltaExplicar): CashRegister {
            $cashRegister = CashRegister::query()->lockForUpdate()->findOrFail($cashRegister->id);

            if (! $cashRegister->estaAbierto()) {
                throw ValidationException::withMessages([
                    'cash_register' => 'El turno de caja no se encuentra abierto',
                ]);
            }

            $conteo = $cashRegister->conteoVigente() ?? $this->registrarConteo($cashRegister, $montoContadoCierre, $denominaciones);
            $contado = round((float) $conteo->monto, 2);
            $montoEsperadoCalculado = $cashRegister->efectivoEsperado();
            $diferencia = round($contado - $montoEsperadoCalculado, 2);

            // El conteo ya quedó guardado; falta que el vendedor diga por qué no cuadra.
            if ($diferencia !== 0.0 && blank($observacion)) {
                $faltaExplicar = true;

                return $cashRegister;
            }

            $cashRegister->update([
                'fecha_cierre' => now(),
                'monto_contado_cierre' => $contado,
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
                        'contado' => $contado,
                        'diferencia' => $diferencia,
                        'motivo' => $observacion,
                    ],
                    userId: $cashRegister->vendedor_id,
                );
            }

            return $cashRegister->fresh();
        });

        // Fuera de la transacción, para que el conteo no se pierda con el rechazo.
        if ($faltaExplicar) {
            throw ValidationException::withMessages([
                'observacion' => 'Tu conteo quedó registrado y no coincide con lo que registró el sistema. Explica qué pasó para cerrar el turno.',
            ]);
        }

        return $resultado;
    }

    /**
     * @param  array<string, int>|null  $denominaciones
     */
    private function registrarConteo(CashRegister $cashRegister, float $monto, ?array $denominaciones): CashRegisterCount
    {
        $lineas = collect($denominaciones ?? [])
            ->filter(fn ($cantidad) => (int) $cantidad > 0)
            ->map(fn ($cantidad, $centimos) => ['denominacion' => (int) $centimos / 100, 'cantidad' => (int) $cantidad]);

        $conteo = $cashRegister->conteos()->create([
            'user_id' => $cashRegister->vendedor_id,
            'monto' => $denominaciones === null ? $monto : round($lineas->sum(fn ($l) => $l['denominacion'] * $l['cantidad']), 2),
        ]);
        $conteo->lineas()->createMany($lineas->values()->all());

        return $conteo;
    }
}
