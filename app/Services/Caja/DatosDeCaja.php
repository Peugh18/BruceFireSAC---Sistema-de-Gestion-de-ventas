<?php

namespace App\Services\Caja;

use App\Models\CashRegister;
use App\Models\SalePayment;
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
                // Arqueo ciego (E-011): el vendedor no recibe el esperado ni la diferencia.
                'monto_contado_cierre' => $cr->monto_contado_cierre,
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
                'abierto_mas_de_24_horas' => $turnoActual->fecha_apertura->lt(now()->subHours(24)),
                'monto_apertura' => $turnoActual->monto_apertura,
                'estado' => $turnoActual->estado,
                'conteo_registrado' => $turnoActual->conteoVigente() !== null,
                'ventas_por_forma_pago' => $ventasPorFormaPago,
                'salidas' => $turnoActual->salidas()->latest('id')->get()->map(fn ($salida) => [
                    'id' => $salida->id,
                    'monto' => (float) $salida->monto,
                    'motivo' => $salida->motivo,
                    'hora' => $salida->created_at?->format('H:i'),
                ])->values(),
                'movimientos' => self::movimientos($turnoActual),
            ] : null,
            'cierres' => $cierres,
            'sedes' => $sedes,
        ];
    }

    /**
     * Lo que pasó en el turno, de lo último a lo primero: cobros, ventas y
     * salidas. El efectivo va sin monto: con arqueo ciego (E-011) el vendedor
     * no puede sumar lo que debería haber en el cajón.
     *
     * @return array<int, array<string, mixed>>
     */
    protected static function movimientos(CashRegister $turno): array
    {
        $pagos = SalePayment::query()
            ->with('sale:id,numero_interno,client_id', 'sale.client:id,razon_social')
            ->where('cash_register_id', $turno->id)
            ->where('es_detraccion', false)
            ->get()
            ->map(fn (SalePayment $pago) => [
                'id' => 'pago-'.$pago->id,
                'momento' => $pago->created_at?->toIso8601String(),
                'hora' => $pago->created_at?->format('H:i'),
                'tipo' => $pago->installment_id ? 'cobro' : 'venta',
                'titulo' => ($pago->installment_id ? 'Cobro' : 'Venta').' · '.($pago->sale->client->razon_social ?? 'Cliente'),
                'detalle' => self::FORMAS[$pago->forma_pago] ?? ucfirst((string) $pago->forma_pago),
                'comprobante' => $pago->sale->numero_interno,
                'efectivo' => $pago->forma_pago === 'efectivo',
                'monto' => $pago->forma_pago === 'efectivo' ? null : (float) $pago->monto,
            ]);

        $salidas = $turno->salidas()->get()->map(fn ($salida) => [
            'id' => 'salida-'.$salida->id,
            'momento' => $salida->created_at?->toIso8601String(),
            'hora' => $salida->created_at?->format('H:i'),
            'tipo' => 'salida',
            'titulo' => 'Salida de dinero',
            'detalle' => $salida->motivo,
            'comprobante' => null,
            'efectivo' => true,
            'monto' => (float) $salida->monto,
        ]);

        return $pagos->concat($salidas)
            ->push([
                'id' => 'apertura',
                'momento' => $turno->fecha_apertura->toIso8601String(),
                'hora' => $turno->fecha_apertura->format('H:i'),
                'tipo' => 'apertura',
                'titulo' => 'Apertura del turno',
                'detalle' => 'Fondo inicial contado',
                'comprobante' => null,
                'efectivo' => true,
                'monto' => (float) $turno->monto_apertura,
            ])
            ->sortByDesc('momento')
            ->values()
            ->all();
    }

    private const FORMAS = [
        'efectivo' => 'Efectivo',
        'transferencia' => 'Transferencia',
        'deposito' => 'Depósito',
        'tarjeta_yape' => 'Tarjeta o Yape',
        'yape' => 'Yape',
        'plin' => 'Plin',
        'pos' => 'Tarjeta (POS)',
        'otro' => 'Otro medio',
    ];
}
