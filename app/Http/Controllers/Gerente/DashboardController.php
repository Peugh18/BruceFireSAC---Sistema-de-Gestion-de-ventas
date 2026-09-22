<?php

namespace App\Http\Controllers\Gerente;

use App\Http\Controllers\Controller;
use App\Models\ElectronicDocument;
use App\Models\Equipment;
use App\Models\Installment;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\Quote;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SalePayment;
use App\Models\ServiceOrder;
use App\Models\Team;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Dashboard del rol Gerente (§5.1 y §86.4.1).
     * Métricas y gráficos gerenciales consolidados de toda la empresa.
     */
    public function __invoke(Team $current_team, Request $request): Response
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
            ->selectRaw('tipo_servicio, count(*) as cantidad')
            ->groupBy('tipo_servicio')
            ->get()
            ->map(fn ($row) => [
                'tipo' => ucfirst(str_replace('_', ' ', (string) $row->tipo_servicio)),
                'cantidad' => (int) $row->cantidad,
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
        ]);
    }
}
