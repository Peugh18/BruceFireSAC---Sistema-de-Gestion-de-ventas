<?php

namespace App\Http\Controllers\Gerente;

use App\Http\Controllers\Controller;
use App\Models\Installment;
use App\Models\SalePayment;
use App\Models\Team;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CollectionConsolidatedController extends Controller
{
    /** Cuotas que todavía se deben (del todo o en parte). */
    public const DEBEN = ['pendiente', 'parcial', 'vencido'];

    /**
     * Lo que falta cobrar de las cuotas: su monto menos lo ya pagado.
     *
     * @param  Builder<Installment>  $cuotas
     */
    public static function saldo(Builder $cuotas): float
    {
        $ids = (clone $cuotas)->pluck('installments.id');

        // V4/S11: menos lo rebajado por notas de crédito aceptadas.
        return round((float) (clone $cuotas)->sum('installments.monto') - (float) (clone $cuotas)->sum('installments.monto_acreditado') - (float) SalePayment::query()->whereIn('installment_id', $ids)->sum('monto'), 2);
    }

    /**
     * Cartera consolidada de cobranzas cruzando todos los vendedores (§32).
     */
    public function index(Team $current_team, Request $request): Response
    {
        $hoy = today();

        $buscar = trim((string) $request->input('buscar', ''));
        $vendedorId = $request->input('vendedor_id');
        $estado = (string) $request->input('estado', 'todos');
        $periodo = (string) $request->input('periodo', 'todos');

        // Solo cuotas de ventas emitidas: un borrador o una venta anulada no
        // es deuda del cliente.
        $query = Installment::query()
            ->whereHas('sale', fn ($sq) => $sq->where('estado', 'confirmada'))
            ->with([
                'sale.client:id,razon_social,numero_documento,telefono',
                'sale.vendedor:id,name',
                'payments' => fn ($pq) => $pq->orderBy('fecha'),
            ])
            ->when($buscar !== '', function ($q) use ($buscar) {
                $q->whereHas('sale', function ($sq) use ($buscar) {
                    $sq->where('numero_interno', 'like', "%{$buscar}%")
                        ->orWhereHas('client', function ($cq) use ($buscar) {
                            $cq->where('razon_social', 'like', "%{$buscar}%")
                                ->orWhere('numero_documento', 'like', "%{$buscar}%");
                        });
                });
            })
            ->when($vendedorId, function ($q) use ($vendedorId) {
                $q->whereHas('sale', fn ($sq) => $sq->where('vendedor_id', $vendedorId));
            })
            ->when($estado !== 'todos', fn ($q) => $q->where('estado', $estado))
            // Vencida es la que ya pasó su fecha y no se terminó de pagar,
            // aunque tenga un pago parcial.
            ->when($periodo === 'vencido', fn ($q) => $q->whereIn('estado', self::DEBEN)->whereDate('fecha_vencimiento', '<', $hoy))
            ->when($periodo === 'vence_semana', function ($q) use ($hoy) {
                $q->whereIn('estado', ['pendiente', 'parcial'])
                    ->whereBetween('fecha_vencimiento', [$hoy, $hoy->copy()->endOfWeek()]);
            })
            ->when($periodo === 'mes', function ($q) {
                $q->whereBetween('fecha_vencimiento', [now()->startOfMonth(), now()->endOfMonth()]);
            })
            ->orderBy('fecha_vencimiento');

        $cuotas = $query->paginate(15)->withQueryString()->through(function (Installment $inst) use ($hoy) {
            $fechaVenc = Carbon::parse($inst->fecha_vencimiento);
            $diasVencido = $fechaVenc->isPast() && ! $fechaVenc->isToday()
                ? (int) $fechaVenc->diffInDays($hoy)
                : 0;

            $pagos = $inst->payments
                ->map(fn (SalePayment $p) => [
                    'id' => $p->id,
                    'forma_pago' => $p->forma_pago,
                    'monto' => (float) $p->monto,
                    'numero_operacion' => $p->numero_operacion,
                    'fecha' => $p->fecha?->toDateString(),
                ])
                ->values()
                ->all();

            $totalPagado = array_sum(array_column($pagos, 'monto'));
            $saldoPendiente = $inst->saldo();

            return [
                'id' => $inst->id,
                'sale_id' => $inst->sale_id,
                'sale_numero' => $inst->sale->numero_interno,
                'numero_cuota' => $inst->numero_cuota,
                'monto' => (float) $inst->monto,
                'monto_pagado' => round($totalPagado, 2),
                'monto_acreditado' => (float) $inst->monto_acreditado,
                'saldo_pendiente' => round($saldoPendiente, 2),
                'fecha_vencimiento' => $fechaVenc->toDateString(),
                'estado' => $inst->estado,
                'dias_vencido' => $diasVencido,
                'cliente' => [
                    'id' => $inst->sale->client?->id,
                    'razon_social' => $inst->sale->client?->razon_social ?? 'Cliente sin nombre',
                    'numero_documento' => $inst->sale->client?->numero_documento,
                    'telefono' => $inst->sale->client?->telefono,
                ],
                'vendedor' => [
                    'id' => $inst->sale->vendedor?->id,
                    'name' => $inst->sale->vendedor?->name ?? 'Vendedor no asignado',
                ],
                'pagos' => $pagos,
            ];
        });

        // KPIs consolidados (§32): por el saldo que falta cobrar, no por el
        // monto original de la cuota (las parciales ya tienen algo pagado).
        $deuda = fn () => Installment::query()
            ->whereIn('installments.estado', self::DEBEN)
            ->whereHas('sale', fn ($sq) => $sq->where('estado', 'confirmada'));

        $totalPorCobrar = self::saldo($deuda());
        $vencidoTotal = self::saldo($deuda()->whereDate('fecha_vencimiento', '<', $hoy));
        $venceEstaSemana = self::saldo($deuda()->whereBetween('fecha_vencimiento', [$hoy, $hoy->copy()->endOfWeek()]));

        $cobradoEsteMes = (float) SalePayment::query()
            ->whereBetween('fecha', [now()->startOfMonth(), now()->endOfMonth()])
            ->sum('monto');

        $clientesConDeuda = Installment::query()
            ->whereIn('installments.estado', self::DEBEN)
            ->join('sales', 'sales.id', '=', 'installments.sale_id')
            ->where('sales.estado', 'confirmada')
            ->distinct('sales.client_id')
            ->count('sales.client_id');

        $vendedores = User::role('Vendedor')
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        return Inertia::render('gerente/cobranzas/index', [
            'cuotas' => $cuotas,
            'filters' => [
                'buscar' => $buscar,
                'vendedor_id' => $vendedorId,
                'estado' => $estado,
                'periodo' => $periodo,
            ],
            'kpis' => [
                'totalPorCobrar' => round($totalPorCobrar, 2),
                'vencidoTotal' => round($vencidoTotal, 2),
                'venceEstaSemana' => round($venceEstaSemana, 2),
                'cobradoEsteMes' => round($cobradoEsteMes, 2),
                'clientesConDeuda' => $clientesConDeuda,
            ],
            'vendedores' => $vendedores,
        ]);
    }
}
