<?php

namespace App\Http\Controllers\Gerente;

use App\Http\Controllers\Controller;
use App\Models\ClientRetentionScore;
use App\Models\ElectronicDocument;
use App\Models\Equipment;
use App\Models\Installment;
use App\Models\InventoryMovement;
use App\Models\MlEntrenamiento;
use App\Models\NoteRequest;
use App\Models\Product;
use App\Models\Quote;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SalePayment;
use App\Models\SaleRefund;
use App\Models\ServiceOrder;
use App\Models\Team;
use App\Services\Ml\RetentionModel;
use App\Services\SaludDelSistema;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Dashboard del rol Gerente (§5.1 y §86.4.1).
     * Métricas y gráficos gerenciales consolidados de toda la empresa.
     */
    public function __invoke(Team $current_team, Request $request, SaludDelSistema $saludDelSistema): Response
    {
        $hoy = today();
        $inicioMes = now()->startOfMonth();
        $finMes = now()->endOfMonth();

        // Abrir el Inicio no cambia datos: la tarea nocturna marca las cuotas
        // vencidas; aquí se calcula por fecha. Solo cuentan las ventas
        // emitidas (ni borradores ni anuladas), igual que en el Vendedor.

        // 1. Tarjetas KPI
        $ventasDia = (float) Sale::query()
            ->whereDate('fecha', $hoy)
            ->where('estado', 'confirmada')
            ->sum('total');

        $ventasMes = (float) Sale::query()
            ->whereBetween('fecha', [$inicioMes, $finMes])
            ->where('estado', 'confirmada')
            ->sum('total');

        $facturacionMes = (float) Sale::query()
            ->whereIn('comprobante_tipo', ['factura', 'boleta'])
            ->whereBetween('fecha', [$inicioMes, $finMes])
            ->where('estado', 'confirmada')
            ->sum('total');

        // Cobrado en el mes menos lo devuelto a clientes.
        $montoCobrado = (float) SalePayment::query()
            ->whereBetween('fecha', [$inicioMes, $finMes])
            ->sum('monto')
            - (float) SaleRefund::query()
                ->whereBetween('fecha', [$inicioMes, $finMes])
                ->sum('monto');

        // Por el saldo que falta cobrar, igual que en Cobranzas.
        $deuda = fn () => Installment::query()
            ->whereIn('installments.estado', CollectionConsolidatedController::DEBEN)
            ->whereHas('sale', fn ($q) => $q->where('estado', 'confirmada'));
        $cuentasPorCobrar = CollectionConsolidatedController::saldo($deuda());
        $vencidoPorCobrar = CollectionConsolidatedController::saldo($deuda()->whereDate('fecha_vencimiento', '<', $hoy));

        // Ofrecidas al cliente y esperando su respuesta.
        $cotizacionesPendientes = Quote::query()
            ->whereIn('estado', ['emitida', 'enviada'])
            ->count();

        $totalCotizaciones = Quote::query()->whereNotIn('estado', ['borrador', 'anulada'])->count();
        $cotizacionesGanadas = Quote::query()
            ->whereIn('estado', ['convertida', 'aceptada'])
            ->count();
        $tasaConversion = $totalCotizaciones > 0
            ? round(($cotizacionesGanadas / $totalCotizaciones) * 100, 1)
            : 0.0;

        $ordenesEnProceso = ServiceOrder::query()
            ->whereNotIn('estado', ['entregado', 'cerrado', 'anulada'])
            ->count();

        $equiposProximosAtencion = Equipment::query()
            ->whereNotNull('proxima_fecha_atencion')
            ->whereBetween('proxima_fecha_atencion', [$hoy, $hoy->copy()->addDays(30)])
            ->count();

        $stockCritico = Product::query()->bajoMinimo()->count();

        $documentosSunatError = ElectronicDocument::query()
            ->whereIn('sunat_estado', ['rechazado', 'excepcion', 'error'])
            ->count();

        // 2. Gráficos
        // Ventas mensuales (últimos 6 meses)
        $ventasMensuales = [];
        for ($i = 5; $i >= 0; $i--) {
            $mesTarget = now()->subMonths($i);
            $inicioTarget = $mesTarget->copy()->startOfMonth();
            $finTarget = $mesTarget->copy()->endOfMonth();

            $montoMes = (float) Sale::query()
                ->whereBetween('fecha', [$inicioTarget, $finTarget])
                ->where('estado', 'confirmada')
                ->sum('total');

            $ventasMensuales[] = [
                'mes' => $mesTarget->translatedFormat('M Y'),
                'monto' => round($montoMes, 2),
            ];
        }

        // Ventas por producto / servicio (top 5) de los últimos 12 meses,
        // sumadas en la base (no se cargan todas las líneas en memoria).
        $desdeAnio = now()->subMonths(11)->startOfMonth();
        $ventasPorItem = SaleItem::query()
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->leftJoin('products', 'products.id', '=', 'sale_items.product_id')
            ->leftJoin('services', 'services.id', '=', 'sale_items.service_id')
            ->where('sales.estado', 'confirmada')
            ->where('sales.fecha', '>=', $desdeAnio)
            ->selectRaw("COALESCE(products.nombre, services.nombre, 'Ítem') as nombre, SUM(sale_items.subtotal) as monto")
            ->groupByRaw("COALESCE(products.nombre, services.nombre, 'Ítem')")
            ->orderByDesc('monto')
            ->limit(5)
            ->get()
            ->map(fn ($row) => [
                'nombre' => (string) $row->getAttribute('nombre'),
                'monto' => round((float) $row->getAttribute('monto'), 2),
            ])
            ->all();

        // Servicios por tipo
        $serviciosPorTipo = ServiceOrder::query()
            ->join('services', 'services.id', '=', 'service_orders.service_id')
            ->selectRaw('services.nombre as tipo_servicio, count(*) as cantidad')
            ->groupBy('services.id', 'services.nombre')
            ->get()
            ->map(fn ($row) => [
                'tipo' => ucfirst(str_replace('_', ' ', (string) $row->getAttribute('tipo_servicio'))),
                'cantidad' => (int) $row->getAttribute('cantidad'),
            ])
            ->all();

        // Cartera por estado
        $carteraAlDia = CollectionConsolidatedController::saldo($deuda()->whereDate('fecha_vencimiento', '>=', $hoy));

        $carteraPorEstado = [
            ['estado' => 'Al Día', 'monto' => round($carteraAlDia, 2)],
            ['estado' => 'Vencido', 'monto' => round($vencidoPorCobrar, 2)],
            ['estado' => 'Cobrado Mes', 'monto' => round($montoCobrado, 2)],
        ];

        // Top 5 clientes de los últimos 12 meses
        $topClientes = Sale::query()
            ->where('sales.estado', 'confirmada')
            ->where('sales.fecha', '>=', $desdeAnio)
            ->join('clients', 'clients.id', '=', 'sales.client_id')
            ->selectRaw('clients.razon_social as cliente, sum(sales.total) as total')
            ->groupBy('clients.id', 'clients.razon_social')
            ->orderByDesc('total')
            ->limit(5)
            ->get()
            ->map(fn ($row) => [
                'cliente' => (string) $row->cliente,
                'total' => round((float) $row->total, 2),
            ])
            ->all();

        // Productos / repuestos con mayor movimiento (salidas de stock)
        $productosMayorMovimiento = InventoryMovement::query()
            ->where('inventory_movements.tipo', 'salida_venta')
            ->join('products', 'products.id', '=', 'inventory_movements.product_id')
            ->selectRaw('products.nombre as producto, sum(abs(inventory_movements.cantidad)) as cantidad')
            ->groupBy('products.id', 'products.nombre')
            ->orderByDesc('cantidad')
            ->limit(5)
            ->get()
            ->map(fn ($row) => [
                'producto' => (string) $row->producto,
                'cantidad' => (int) $row->cantidad,
            ])
            ->all();

        // 3. IA Predictiva: Modelo de Retención y Recompra (§39.1)
        $retentionModel = app(RetentionModel::class);
        $totalScores = ClientRetentionScore::query()->count();

        $aiRetention = null;
        if ($totalScores > 0) {
            $conteoAlta = ClientRetentionScore::query()->where('categoria', 'alta')->count();
            $conteoMedia = ClientRetentionScore::query()->where('categoria', 'media')->count();
            $conteoBaja = ClientRetentionScore::query()->where('categoria', 'baja')->count();

            $topRecompra = ClientRetentionScore::query()
                ->with(['client:id,razon_social,numero_documento,telefono,whatsapp'])
                ->orderByDesc('probabilidad')
                ->limit(6)
                ->get()
                ->map(fn (ClientRetentionScore $score) => [
                    'clientId' => $score->client_id,
                    'cliente' => $score->client?->razon_social ?? 'Cliente #'.$score->client_id,
                    'documento' => $score->client?->numero_documento ?? '-',
                    'telefono' => $score->client?->telefono ?? $score->client?->whatsapp,
                    'probabilidad' => round($score->probabilidad * 100, 1),
                    'categoria' => $score->categoria,
                    'recenciaDias' => $score->recencia_dias,
                    'frecuencia' => $score->frecuencia_compras,
                    'montoTotal' => round((float) $score->monto_total, 2),
                    'ticketPromedio' => round((float) $score->ticket_promedio, 2),
                    'comproRecarga' => (bool) $score->compro_recarga,
                    'factores' => $score->factores_json ?? ['positivos' => [], 'negativos' => []],
                ])
                ->all();

            $modelMetadata = $retentionModel->getModelMetadata();

            $aiRetention = [
                'totalEvaluados' => $totalScores,
                'distribucion' => [
                    'alta' => ['cantidad' => $conteoAlta, 'porcentaje' => round(($conteoAlta / $totalScores) * 100, 1)],
                    'media' => ['cantidad' => $conteoMedia, 'porcentaje' => round(($conteoMedia / $totalScores) * 100, 1)],
                    'baja' => ['cantidad' => $conteoBaja, 'porcentaje' => round(($conteoBaja / $totalScores) * 100, 1)],
                ],
                'topClientes' => $topRecompra,
                'modelo' => [
                    'disponible' => true,
                    'nombre' => ($modelMetadata['type'] ?? 'logistic') === 'xgboost' ? 'XGBoost' : 'Regresión logística',
                    'aucRoc' => (float) ($modelMetadata['metrics']['test']['roc_auc'] ?? 0),
                    'accuracy' => (float) ($modelMetadata['metrics']['test']['accuracy'] ?? 0),
                    'precision' => (float) ($modelMetadata['metrics']['test']['precision'] ?? 0),
                    'recall' => (float) ($modelMetadata['metrics']['test']['recall'] ?? 0),
                    'fechaEntrenamiento' => $modelMetadata['trained_at'] ?? null,
                    // Precisión de cada reentrenamiento (ml:reentrenar), del más antiguo al último.
                    'historial' => MlEntrenamiento::query()->latest('entrenado_at')->limit(12)->get()->reverse()->values()
                        ->map(fn (MlEntrenamiento $entrenamiento) => [
                            'fecha' => $entrenamiento->entrenado_at->toDateString(),
                            'modelo' => $entrenamiento->modelo === 'xgboost' ? 'XGBoost' : 'Regresión logística',
                            'aucRoc' => (float) $entrenamiento->roc_auc,
                            'top100' => $entrenamiento->top_100_volvieron,
                        ])->all(),
                ],
            ];
        }

        return Inertia::render('gerente/dashboard', [
            'metrics' => [
                'ventasDia' => round($ventasDia, 2),
                'ventasMes' => round($ventasMes, 2),
                'facturacionMes' => round($facturacionMes, 2),
                'montoCobrado' => round($montoCobrado, 2),
                'cuentasPorCobrar' => round($cuentasPorCobrar, 2),
                'vencidoPorCobrar' => round($vencidoPorCobrar, 2),
                'cotizacionesPendientes' => $cotizacionesPendientes,
                'tasaConversion' => $tasaConversion,
                'ordenesEnProceso' => $ordenesEnProceso,
                'equiposProximosAtencion' => $equiposProximosAtencion,
                'stockCritico' => $stockCritico,
                'documentosSunatError' => $documentosSunatError,
                'notasPorAprobar' => NoteRequest::query()->where('estado', 'por_aprobar')->count(),
                // S8: comprobantes que aún no llegan a SUNAT y vencen hoy o mañana, o ya vencieron.
                'plazoSunat' => [
                    'por_vencer' => ElectronicDocument::query()->porVencerSunat()->count(),
                    'vencidos' => ElectronicDocument::query()->vencidosSunat()->count(),
                ],
            ],
            'charts' => [
                'ventasMensuales' => $ventasMensuales,
                'ventasPorItem' => $ventasPorItem,
                'serviciosPorTipo' => $serviciosPorTipo,
                'carteraPorEstado' => $carteraPorEstado,
                'topClientes' => $topClientes,
                'productosMayorMovimiento' => $productosMayorMovimiento,
            ],
            'aiRetention' => $aiRetention,
            'sistema' => $saludDelSistema->resumen(),
        ]);
    }
}
