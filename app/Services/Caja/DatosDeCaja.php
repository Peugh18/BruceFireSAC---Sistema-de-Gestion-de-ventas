<?php

namespace App\Services\Caja;

use App\Models\CashRegister;
use App\Models\Sede;
use App\Models\User;

/**
 * Datos del turno de caja de un vendedor: turno abierto con lo cobrado por
 * forma de pago, cierres pasados y sedes. Las pantallas de Caja y Cobranzas
 * comparten estos datos (antes vivían como método estático del controlador
 * de Caja y ambos controladores se llamaban entre sí).
 */
class DatosDeCaja
{
    /**
     * Turno abierto con lo cobrado por forma de pago, cierres pasados y sedes.
     *
     * @return array<string, mixed>
     */
    public static function para(User $user): array
    {
        /** @var CashRegister|null $turnoActual */
        $turnoActual = CashRegister::query()
            ->with('sede')
            ->where('vendedor_id', $user->id)
            ->where('estado', 'abierto')
            ->first();

        $ventasPorFormaPago = [];
        if ($turnoActual) {
            $ventasPorFormaPago = $turnoActual->movimientosPorFormaDePago();
        }

        $cierres = CashRegister::query()
            ->with('sede')
            ->where('vendedor_id', $user->id)
            ->where('estado', 'cerrado')
            ->orderByDesc('fecha_cierre')
            ->paginate(10, ['*'], 'cierres_page')
            ->withQueryString()
            ->through(fn (CashRegister $cr) => [
                'id' => $cr->id,
                'sede' => $cr->sede?->nombre,
                'fecha_apertura' => $cr->fecha_apertura->toDateTimeString(),
                'fecha_cierre' => $cr->fecha_cierre?->toDateTimeString(),
                'monto_apertura' => $cr->monto_apertura,
                'monto_contado_cierre' => $cr->monto_contado_cierre,
                'monto_esperado_calculado' => $cr->monto_esperado_calculado,
                'diferencia' => $cr->diferencia,
                'observacion' => $cr->observacion,
                'estado' => $cr->estado,
            ]);

        $sedeRestringida = $user->sedeRestringidaId();
        $sedes = Sede::query()
            ->where('activo', true)
            ->when($sedeRestringida, fn ($q) => $q->where('id', $sedeRestringida))
            ->get(['id', 'nombre', 'tipo']);

        return [
            'turno_actual' => $turnoActual ? [
                'id' => $turnoActual->id,
                'sede' => $turnoActual->sede?->nombre,
                'sede_id' => $turnoActual->sede_id,
                'fecha_apertura' => $turnoActual->fecha_apertura->toDateTimeString(),
                'monto_apertura' => $turnoActual->monto_apertura,
                'estado' => $turnoActual->estado,
                'ventas_por_forma_pago' => $ventasPorFormaPago,
            ] : null,
            'cierres' => $cierres,
            'sedes' => $sedes,
        ];
    }
}
