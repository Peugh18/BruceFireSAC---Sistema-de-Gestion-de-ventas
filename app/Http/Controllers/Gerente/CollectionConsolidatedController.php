<?php

namespace App\Http\Controllers\Gerente;

use App\Http\Controllers\Controller;
use App\Models\Installment;
use App\Models\SalePayment;
use App\Models\Team;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CollectionConsolidatedController extends Controller
{
    /**
     * Cartera consolidada de cobranzas cruzando todos los vendedores (§32).
     */
    public function index(Team $current_team, Request $request): Response
    {
        $hoy = today();

        // 1. Actualizar a vencido las cuotas vencidas
        Installment::query()
            ->where('estado', 'pendiente')
            ->whereDate('fecha_vencimiento', '<', $hoy)
            ->update(['estado' => 'vencido']);

        $buscar = trim((string) $request->input('buscar', ''));
        $vendedorId = $request->input('vendedor_id');
        $estado = (string) $request->input('estado', 'todos');
        $periodo = (string) $request->input('periodo', 'todos');

        $query = Installment::query()
            ->with([
                'sale.client:id,razon_social,numero_documento,telefono',
                'sale.vendedor:id,name',
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
            ->when($periodo === 'vencido', fn ($q) => $q->where('estado', 'vencido'))
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

            // Pagos registrados
            $pagos = SalePayment::query()
                ->where('installment_id', $inst->id)
                ->orderBy('fecha')
                ->get()
                ->map(fn (SalePayment $p) => [
                    'id' => $p->id,
                    'forma_pago' => $p->forma_pago,
                    'monto' => (float) $p->monto,
                    'numero_operacion' => $p->numero_operacion,
                    'fecha' => $p->fecha?->toDateString(),
                ])
                ->all();

            $totalPagado = array_sum(array_column($pagos, 'monto'));
            $saldoPendiente = max(0, (float) $inst->monto - $totalPagado);

            return [
                'id' => $inst->id,
                'sale_id' => $inst->sale_id,
                'sale_numero' => $inst->sale->numero_interno,
                'numero_cuota' => $inst->numero_cuota,
                'monto' => (float) $inst->monto,
                'monto_pagado' => round($totalPagado, 2),
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

        // KPIs consolidados (§32)
        $totalPorCobrar = (float) Installment::query()
            ->whereIn('estado', ['pendiente', 'parcial', 'vencido'])
            ->sum('monto');

        $vencidoTotal = (float) Installment::query()
            ->where('estado', 'vencido')
            ->sum('monto');

        $venceEstaSemana = (float) Installment::query()
            ->whereIn('estado', ['pendiente', 'parcial'])
            ->whereBetween('fecha_vencimiento', [$hoy, $hoy->copy()->endOfWeek()])
            ->sum('monto');

        $cobradoEsteMes = (float) SalePayment::query()
            ->whereBetween('fecha', [now()->startOfMonth(), now()->endOfMonth()])
            ->sum('monto');

        $clientesConDeuda = Installment::query()
            ->whereIn('installments.estado', ['pendiente', 'parcial', 'vencido'])
            ->join('sales', 'sales.id', '=', 'installments.sale_id')
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
