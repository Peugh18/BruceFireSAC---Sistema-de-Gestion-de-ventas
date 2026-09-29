<?php

namespace App\Http\Controllers\Gerente;

use App\Http\Controllers\Controller;
use App\Models\ClientRetentionScore;
use App\Models\ElectronicDocument;
use App\Models\Equipment;
use App\Models\Installment;
use App\Models\InventoryMovement;
use App\Models\MlEntrenamiento;
use App\Models\Product;
use App\Models\Quote;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SalePayment;
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

        // Actualizar cuotas vencidas antes de calcular
        Installment::query()
            ->where('estado', 'pendiente')
            ->whereDate('fecha_vencimiento', '<', $hoy)
            ->update(['estado' => 'vencido']);

        // 1. Tarjetas KPI
        $ventasDia = (float) Sale::query()
            ->whereDate('fecha', $hoy)
            ->where('estado', '!=', 'anulada')
            ->sum('total');

        $ventasMes = (float) Sale::query()
            ->whereBetween('fecha', [$inicioMes, $finMes])
            ->where('estado', '!=', 'anulada')
            ->sum('total');

        $facturacionMes = (float) Sale::query()
            ->whereIn('comprobante_tipo', ['factura', 'boleta'])
            ->whereBetween('fecha', [$inicioMes, $finMes])
            ->where('estado', '!=', 'anulada')
            ->sum('total');

        $montoCobrado = (float) SalePayment::query()
            ->whereBetween('fecha', [$inicioMes, $finMes])
            ->sum('monto');

        $cuentasPorCobrar = (float) Installment::query()
            ->whereIn('estado', ['pendiente', 'vencido'])
            ->sum('monto');

        $vencidoPorCobrar = (float) Installment::query()
            ->where('estado', 'vencido')
            ->sum('monto');

        $cotizacionesPendientes = Quote::query()
            ->where('estado', 'pendiente')
            ->count();

        $totalCotizaciones = Quote::query()->count();
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

        $stockCritico = Product::query()
            ->where('activo', true)
            ->whereNotNull('stock_minimo')
            ->where('stock_minimo', '>', 0)
            ->withCount(['units as stock_disponible' => function ($query) {
                $query->where('estado', 'disponible');
            }])
            ->get()
            ->filter(fn ($p) => $p->stock_disponible <= $p->stock_minimo)
            ->count();

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
                ->where('estado', '!=', 'anulada')
                ->sum('total');

            $ventasMensuales[] = [
                'mes' => $mesTarget->translatedFormat('M Y'),
                'monto' => round($montoMes, 2),
            ];
        }

        // Ventas por producto / servicio (top 5)
        $ventasPorItem = SaleItem::query()
            ->with(['product:id,nombre', 'service:id,nombre'])
            ->whereHas('sale', fn ($q) => $q->where('estado', '!=', 'anulada'))
            ->get()
            ->groupBy(function (SaleItem $item) {
                return $item->product?->nombre ?? $item->service?->nombre ?? 'Ítem';
            })
            ->map(function ($items, $nombre) {
                return [
                    'nombre' => (string) $nombre,
                    'monto' => round((float) $items->sum('subtotal'), 2),
                ];
            })
            ->sortByDesc('monto')
            ->take(5)
            ->values()
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
        $carteraAlDia = (float) Installment::query()
            ->where('estado', 'pendiente')
            ->whereDate('fecha_vencimiento', '>=', $hoy)
            ->sum('monto');

        $carteraPorEstado = [
            ['estado' => 'Al Día', 'monto' => round($carteraAlDia, 2)],
            ['estado' => 'Vencido', 'monto' => round($vencidoPorCobrar, 2)],
            ['estado' => 'Cobrado Mes', 'monto' => round($montoCobrado, 2)],
        ];

        // Top 5 clientes por facturación total
        $topClientes = Sale::query()
            ->where('sales.estado', '!=', 'anulada')
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
