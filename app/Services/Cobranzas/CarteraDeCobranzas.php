<?php

namespace App\Services\Cobranzas;

use App\Models\Installment;
use App\Models\Sale;
use App\Models\SalePayment;
use App\Models\SaleRefund;
use App\Models\User;
use App\Services\Billing\DetraccionCalculator;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

/**
 * Reglas compartidas de cobranzas (antes repartidas entre los controladores
 * de Cobranzas del Vendedor, Caja del Vendedor, Cobranzas del Gerente y el
 * Dashboard del Gerente, que se llamaban entre sí por métodos estáticos):
 * cuotas pendientes del vendedor y saldo de la cartera consolidada.
 */
class CarteraDeCobranzas
{
    /** Cuotas que todavía se deben (del todo o en parte). */
    public const DEBEN = ['pendiente', 'parcial', 'vencido'];

    /**
     * Saldos de la venta original, compartidos por todas sus cuotas. La reserva
     * SPOT no es un cobro comercial y no se suma una vez por cada cuota.
     *
     * @return array{comercial:float,detraccion:float}
     */
    public static function saldosDelComprobante(Sale $sale): array
    {
        $sale->loadMissing(['installments.payments', 'payments']);
        $saldo = (float) $sale->installments->filter(fn (Installment $cuota) => $cuota->esDelComprobante())->sum(fn (Installment $cuota) => $cuota->saldo());
        $calculada = app(DetraccionCalculator::class)->paraVenta($sale);
        $acreditado = (float) $sale->payments->where('es_detraccion', true)->sum('monto');
        $detraccion = max(0, round($calculada['monto'] - $acreditado, 2));

        return ['comercial' => max(0, round($saldo - $detraccion, 2)), 'detraccion' => $detraccion];
    }

    /**
     * Lo que falta cobrar de las cuotas: su monto menos lo ya pagado.
     * Los totales salen de la base; no se cargan los IDs de las cuotas.
     *
     * @param  Builder<Installment>  $cuotas
     */
    public static function saldo(Builder $cuotas): float
    {
        $totales = (clone $cuotas)
            ->selectRaw('COALESCE(SUM(installments.monto), 0) as monto_total')
            ->selectRaw('COALESCE(SUM(installments.monto_acreditado), 0) as acreditado_total')
            ->toBase()
            ->first();

        // V4/S11: menos lo rebajado por notas de crédito aceptadas.
        $pagado = SalePayment::query()
            ->whereIn('installment_id', (clone $cuotas)->select('installments.id'))
            ->sum('monto');

        return round(
            (float) ($totales->monto_total ?? 0)
            - (float) ($totales->acreditado_total ?? 0)
            - (float) $pagado,
            2
        );
    }

    /**
     * Lo cobrado entre dos fechas: los abonos recibidos menos lo devuelto a
     * clientes. El panel del Gerente y Cobranzas usan este mismo cálculo para
     * que muestren la misma cifra.
     */
    public static function cobrado(CarbonInterface $desde, CarbonInterface $hasta): float
    {
        return round(
            (float) SalePayment::query()->whereBetween('fecha', [$desde, $hasta])->sum('monto')
            - (float) SaleRefund::query()->whereBetween('fecha', [$desde, $hasta])->sum('monto'),
            2
        );
    }

    /**
     * Cuotas pendientes, parciales o vencidas de las ventas emitidas del
     * vendedor (y las pagadas hoy).
     *
     * @return array<string, mixed>
     */
    public static function cuotasPendientes(User $user): array
    {
        return Installment::query()
            ->where(function ($query) {
                $query->whereIn('estado', ['pendiente', 'parcial', 'vencido'])
                    ->orWhereHas('payments', fn ($payments) => $payments->whereDate('fecha', today()));
            })
            // Solo ventas emitidas: un borrador o una venta anulada no se cobra.
            ->whereHas('sale', fn ($q) => $q->where('vendedor_id', $user->id)
                ->where('estado', 'confirmada')
                ->when($user->sedeRestringidaId(), fn ($sq, $sedeId) => $sq->where('sede_id', $sedeId)))
            ->with([
                'sale.client',
                'sale.installments.payments',
                'sale.payments',
                'payments' => fn ($query) => $query->latest('id'),
                'paymentsAnulados' => fn ($query) => $query->with('anuladoPor:id,name')->latest('deleted_at'),
            ])
            ->orderBy('fecha_vencimiento')
            ->paginate(15)
            ->withQueryString()
            ->through(function (Installment $inst) use ($user) {
                $fechaVenc = Carbon::parse($inst->fecha_vencimiento);
                $diasVencido = $fechaVenc->isPast() && ! $fechaVenc->isToday()
                    ? (int) $fechaVenc->diffInDays(today())
                    : 0;

                $saldo = $inst->saldo();
                $saldos = self::saldosDelComprobante($inst->sale);

                return [
                    'id' => $inst->id,
                    'sale_id' => $inst->sale_id,
                    'sale_numero' => $inst->sale->numero_interno,
                    'con_detraccion' => $inst->esDelComprobante() && $saldos['detraccion'] > 0,
                    'saldo_comercial_venta' => $saldos['comercial'],
                    'saldo_detraccion_venta' => $saldos['detraccion'],
                    'numero_cuota' => $inst->numero_cuota,
                    'monto' => $inst->monto,
                    // V4/S11: lo rebajado por notas de crédito aceptadas.
                    'acreditado' => (float) $inst->monto_acreditado,
                    'saldo' => $saldo,
                    'fecha_vencimiento' => $fechaVenc->toDateString(),
                    'estado' => $inst->estado,
                    'dias_vencido' => $diasVencido,
                    'cliente' => $inst->sale->client->razon_social,
                    'pagos' => $inst->payments->map(fn (SalePayment $payment) => [
                        'id' => $payment->id,
                        'monto' => $payment->monto,
                        'forma_pago' => $payment->es_detraccion ? 'Detracción BN' : $payment->forma_pago,
                        'es_detraccion' => $payment->es_detraccion,
                        'fecha' => $payment->fecha->toDateString(),
                        'numero_operacion' => $payment->numero_operacion,
                        'evidencia_url' => $payment->es_detraccion && $payment->constancia_path !== null ? route('vendedor.ventas.detraccion.evidencia', ['current_team' => $user->currentTeam, 'sale' => $payment->sale_id, 'payment' => $payment->id]) : null,
                        'puede_anular' => $user->hasRole('Gerente') || $payment->fecha->isToday(),
                    ])->values(),
                    // Cobros anulados: quedan a la vista, tachados, con el motivo.
                    'anulados' => $inst->paymentsAnulados->map(fn (SalePayment $payment) => [
                        'id' => $payment->id,
                        'monto' => $payment->monto,
                        'forma_pago' => $payment->es_detraccion ? 'Detracción BN' : $payment->forma_pago,
                        'es_detraccion' => $payment->es_detraccion,
                        'motivo' => $payment->anulado_motivo,
                        'por' => $payment->anuladoPor?->name,
                    ])->values(),
                ];
            })
            ->toArray();
    }
}
