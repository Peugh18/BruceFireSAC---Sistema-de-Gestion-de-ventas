<?php

namespace App\Http\Controllers\Vendedor;

use App\Http\Controllers\Controller;
use App\Models\CashRegister;
use App\Models\ElectronicDocument;
use App\Models\Installment;
use App\Models\Quote;
use App\Models\Sale;
use App\Models\ServiceOrder;
use App\Models\Team;
use App\Services\Avisos\AvisosDelVendedor;
use App\Services\Avisos\ExtintoresPorVencer;
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

        // 1. Ventas emitidas HOY por el vendedor autenticado (no cuentan los
        // borradores ni las anuladas, igual que en la lista de Ventas).
        $ventasHoyQuery = Sale::query()
            ->where('vendedor_id', $user->id)
            ->where('estado', 'confirmada')
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
            // Lo que entró en este turno (menos devoluciones), igual que el
            // cierre de caja: solo desde la apertura, no todo el día.
            $porForma = collect($turnoActual->movimientosPorFormaDePago());
            $sumar = fn (array $formas) => (float) $porForma->only($formas)->sum();

            // Agrupación de formas de pago:
            // - efectivo: dinero en efectivo físico
            // - transferencia: transferencias bancarias y depósitos
            // - tarjeta_yape: POS, Yape, Plin y tarjetas digitales
            // - otros: cheques, notas u otras formas no clasificadas
            $efectivo = $sumar(['efectivo']);
            $transferencia = $sumar(['transferencia', 'deposito']);
            $tarjetaYape = $sumar(['pos', 'yape', 'plin']);
            $otros = (float) $porForma->except(['efectivo', 'transferencia', 'deposito', 'pos', 'yape', 'plin'])->sum();

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

        // 4. Cobros pendientes / vencidos del vendedor autenticado. Abrir el
        // Inicio no cambia datos: la tarea nocturna (alerts:recompute) marca
        // las cuotas vencidas; aquí solo se muestran según su fecha.
        // Las parciales también se deben (por su saldo); solo de ventas emitidas.
        $cobrosPendientes = Installment::query()
            ->whereIn('estado', ['pendiente', 'parcial', 'vencido'])
            ->whereHas('sale', fn ($q) => $q->where('vendedor_id', $user->id)->where('estado', 'confirmada'))
            ->with(['sale.client', 'payments'])
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
                    'monto' => $inst->saldo(),
                    'fecha_vencimiento' => $fechaVenc->toDateString(),
                    'estado' => $diasVencido > 0 ? 'vencido' : $inst->estado,
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
                'tipo_servicio' => $orden->service->nombre,
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
            'por_vencer_semana' => app(ExtintoresPorVencer::class)->segmentos(today(), 5, $user)['esta_semana'],
            'pendientes' => $this->pendientes($user->id, $user->sedeRestringidaId()),
            // Clientes para ofrecer la recarga: con extintores vencidos o que
            // vencen en los próximos 3 meses, del más urgente al más lejano.
            'oportunidades' => collect(app(ExtintoresPorVencer::class)->porEmpresa(today(), user: $user))
                ->filter(fn (array $empresa) => $empresa['vencidos'] > 0 || ($empresa['dias'] !== null && $empresa['dias'] <= 90))
                ->take(5)
                ->map(fn (array $empresa) => collect($empresa)->except('equipos')->all())
                ->values()
                ->all(),
        ]);
    }

    /**
     * Lo que el vendedor tiene que resolver: cada número enlaza a la pantalla
     * donde se atiende.
     *
     * @return array{por_enviar: int, rechazados: int, cotizaciones_aceptadas: int, cuotas_vencidas: int, borradores: int}
     */
    protected function pendientes(int $vendedorId, ?int $sedeId): array
    {
        $documentos = fn (array $estados) => ElectronicDocument::query()
            ->whereIn('tipo', ['factura', 'boleta'])
            ->whereIn('sunat_estado', $estados)
            ->whereHas('sale', fn ($query) => $query->where('vendedor_id', $vendedorId)->where('estado', 'confirmada'))
            ->count();

        return [
            'por_enviar' => $documentos(['por_enviar']),
            // Solo los rechazos que aún no se corrigieron (la venta sigue con
            // ese comprobante como el último).
            'rechazados' => Sale::query()
                ->where('vendedor_id', $vendedorId)
                ->where('estado', 'confirmada')
                ->with('electronicDocuments')
                ->whereHas('electronicDocuments', fn ($query) => $query->whereIn('sunat_estado', ['rechazado', 'excepcion']))
                ->get()
                ->filter(fn (Sale $sale) => (bool) $sale->comprobanteElectronico()?->fueRechazado())
                ->count(),
            'cotizaciones_aceptadas' => Quote::query()
                ->where('estado', 'aceptada')
                ->whereDoesntHave('sale')
                ->when($sedeId, fn ($query) => $query->where('sede_id', $sedeId))
                ->count(),
            'cuotas_vencidas' => Installment::query()
                ->whereIn('estado', ['pendiente', 'parcial', 'vencido'])
                ->whereDate('fecha_vencimiento', '<', today())
                ->whereHas('sale', fn ($query) => $query->where('vendedor_id', $vendedorId)->where('estado', 'confirmada'))
                ->count(),
            'borradores' => Sale::query()->where('vendedor_id', $vendedorId)->where('estado', 'borrador')->count(),
        ];
    }
}
