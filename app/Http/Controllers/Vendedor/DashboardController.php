<?php

namespace App\Http\Controllers\Vendedor;

use App\Http\Controllers\Controller;
use App\Models\CashRegister;
use App\Models\Installment;
use App\Models\Quote;
use App\Models\Sale;
use App\Models\SalePayment;
use App\Models\ServiceOrder;
use App\Models\Team;
use App\Services\Avisos\AvisosDelVendedor;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Dashboard del Vendedor: SOLO métricas del día para el vendedor autenticado.
     * Regla de negocio estricta: NUNCA expone acumulados mensuales ni datos globales de la empresa.
     */
    public function __invoke(Team $current_team, Request $request): Response
    {
        $user = $request->user();

        // 1. Ventas de HOY del vendedor autenticado
        $ventasHoyQuery = Sale::query()
            ->where('vendedor_id', $user->id)
            ->whereDate('fecha', today());

        $ventasHoyTotal = (float) $ventasHoyQuery->sum('total');
        $ventasHoyCount = (int) $ventasHoyQuery->count();
        $ticketPromedio = $ventasHoyCount > 0 ? round($ventasHoyTotal / $ventasHoyCount, 2) : 0.0;

        $ventasHoy = [
            'total' => round($ventasHoyTotal, 2),
            'count' => $ventasHoyCount,
            'ticket_promedio' => $ticketPromedio,
        ];

        // 2. Caja de HOY del vendedor autenticado
        /** @var CashRegister|null $turnoActual */
        $turnoActual = CashRegister::query()
            ->where('vendedor_id', $user->id)
            ->where('estado', 'abierto')
            ->latest('fecha_apertura')
            ->first();

        $cajaHoy = null;
        if ($turnoActual !== null) {
            // Pagos recibidos hoy durante el turno del vendedor
            $pagosHoy = SalePayment::query()
                ->whereHas('sale', fn ($q) => $q->where('vendedor_id', $user->id))
                ->where(function ($query) use ($turnoActual) {
                    $query->whereBetween('created_at', [$turnoActual->fecha_apertura, now()->addMinute()])
                        ->orWhereDate('fecha', today());
                })
                ->get();

            // Agrupación de formas de pago:
            // - efectivo: dinero en efectivo físico
            // - transferencia: transferencias bancarias y depósitos
            // - tarjeta_yape: POS, Yape, Plin y tarjetas digitales
            // - otros: cheques, notas u otras formas no clasificadas
            $efectivo = (float) $pagosHoy->where('forma_pago', 'efectivo')->sum('monto');
            $transferencia = (float) $pagosHoy->whereIn('forma_pago', ['transferencia', 'deposito'])->sum('monto');
            $tarjetaYape = (float) $pagosHoy->whereIn('forma_pago', ['pos', 'yape', 'plin'])->sum('monto');
            $otros = (float) $pagosHoy->whereNotIn('forma_pago', ['efectivo', 'transferencia', 'deposito', 'pos', 'yape', 'plin'])->sum('monto');

            $montoApertura = (float) $turnoActual->monto_apertura;
            $totalEsperadoCorriente = round($montoApertura + $efectivo, 2);

            $cajaHoy = [
                'turno_id' => $turnoActual->id,
                'estado' => $turnoActual->estado,
                'fecha_apertura' => $turnoActual->fecha_apertura?->toDateTimeString(),
                'monto_apertura' => round($montoApertura, 2),
                'total_efectivo' => round($efectivo, 2),
                'total_esperado_corriente' => $totalEsperadoCorriente,
                'por_forma_pago' => [
                    'efectivo' => round($efectivo, 2),
                    'transferencia' => round($transferencia, 2),
                    'tarjeta_yape' => round($tarjetaYape, 2),
                    'otros' => round($otros, 2),
                ],
            ];
        }

        // 3. Cotizaciones de este mes: SOLO conteo por estado, sin montos monetarios
        $cotizacionesMes = Quote::query()
            ->where('vendedor_id', $user->id)
            ->whereBetween('fecha', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()])
            ->selectRaw('estado, count(*) as count')
            ->groupBy('estado')
            ->pluck('count', 'estado')
            ->map(fn ($count) => (int) $count)
            ->all();

        // 4. Cobros pendientes / vencidos del vendedor autenticado
        Installment::query()
            ->where('estado', 'pendiente')
            ->whereDate('fecha_vencimiento', '<', today())
            ->update(['estado' => 'vencido']);

        $cobrosPendientes = Installment::query()
            ->whereIn('estado', ['pendiente', 'vencido'])
            ->whereHas('sale', fn ($q) => $q->where('vendedor_id', $user->id))
            ->with(['sale.client'])
            ->orderBy('fecha_vencimiento')
            ->limit(10)
            ->get()
            ->map(function (Installment $inst) {
                $fechaVenc = Carbon::parse($inst->fecha_vencimiento);
                $diasVencido = $fechaVenc->isPast() && ! $fechaVenc->isToday()
                    ? (int) $fechaVenc->diffInDays(today())
                    : 0;

                return [
                    'id' => $inst->id,
                    'sale_id' => $inst->sale_id,
                    'sale_numero' => $inst->sale?->numero_interno,
                    'numero_cuota' => $inst->numero_cuota,
                    'monto' => (float) $inst->monto,
                    'fecha_vencimiento' => $fechaVenc->toDateString(),
                    'estado' => $inst->estado,
                    'dias_vencido' => $diasVencido,
                    'cliente' => $inst->sale?->client?->razon_social,
                    'telefono' => $inst->sale?->client?->whatsapp ?: $inst->sale?->client?->telefono,
                ];
            })
            ->all();

        $alertasTop = app(AvisosDelVendedor::class)->lista($user, $current_team->slug);

        $agendaHoy = ServiceOrder::query()
            ->with(['client:id,razon_social', 'tecnico:id,name'])
            ->when($user->sedeRestringidaId(), fn ($query, $sedeId) => $query->where('sede_id', $sedeId))
            ->whereDate('fecha', today())
            ->whereNotIn('estado', ['entregado', 'cerrado'])
            ->orderBy('fecha')
            ->orderBy('id')
            ->get()
            ->map(fn (ServiceOrder $orden) => [
                'id' => $orden->id,
                'codigo' => $orden->codigo,
                'cliente' => $orden->client->razon_social,
                'tipo_servicio' => $orden->tipo_servicio,
                'tecnico' => $orden->tecnico?->name,
                'estado' => $orden->estado,
                'prioridad' => $orden->prioridad,
            ])
            ->all();

        return Inertia::render('vendedor/dashboard', [
            'ventas_hoy' => $ventasHoy,
            'caja_hoy' => $cajaHoy,
            'cotizaciones_mes' => $cotizacionesMes,
            'cobros_pendientes' => $cobrosPendientes,
            'alertas_top' => $alertasTop,
            'agenda_hoy' => $agendaHoy,
        ]);
    }
}
