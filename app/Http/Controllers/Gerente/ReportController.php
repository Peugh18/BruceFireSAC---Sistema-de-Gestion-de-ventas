<?php

namespace App\Http\Controllers\Gerente;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Quote;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Sede;
use App\Models\Team;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    /**
     * Centro de reportes gerenciales de comercial e inventario (§34).
     */
    public function index(Team $current_team, Request $request): Response
    {
        $tipo = (string) $request->input('tipo', 'comercial');

        $vendedores = User::role('Vendedor')
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        $sedes = Sede::query()
            ->where('activo', true)
            ->select('id', 'nombre')
            ->orderBy('nombre')
            ->get();

        if ($tipo === 'inventario') {
            $data = $this->getInventarioData($request);

            return Inertia::render('gerente/reportes/index', [
                'tipo' => 'inventario',
                'reporteInventario' => $data,
                'vendedores' => $vendedores,
                'sedes' => $sedes,
                'filters' => [
                    'sede_id' => $request->input('sede_id'),
                    'solo_bajo_minimo' => $request->boolean('solo_bajo_minimo', false),
                ],
            ]);
        }

        $data = $this->getComercialData($request);

        return Inertia::render('gerente/reportes/index', [
            'tipo' => 'comercial',
            'reporteComercial' => $data,
            'vendedores' => $vendedores,
            'sedes' => $sedes,
            'filters' => [
                'fecha_desde' => $request->input('fecha_desde', now()->startOfMonth()->toDateString()),
                'fecha_hasta' => $request->input('fecha_hasta', today()->toDateString()),
                'vendedor_id' => $request->input('vendedor_id'),
            ],
        ]);
    }

    public function exportComercialPdf(Team $current_team, Request $request): HttpResponse
    {
        $data = $this->getComercialData($request);

        $pdf = Pdf::loadView('pdf.reporte-comercial', $data)->setPaper('a4');

        $filename = 'reporte-comercial-'.today()->toDateString().'.pdf';

        return $pdf->download($filename);
    }

    public function exportInventarioPdf(Team $current_team, Request $request): HttpResponse
    {
        $data = $this->getInventarioData($request);

        $pdf = Pdf::loadView('pdf.reporte-inventario', $data)->setPaper('a4');

        $filename = 'reporte-inventario-'.today()->toDateString().'.pdf';

        return $pdf->download($filename);
    }

    /**
     * @return array<string, mixed>
     */
    protected function getComercialData(Request $request): array
    {
        $fechaDesdeInput = $request->input('fecha_desde', now()->startOfMonth()->toDateString());
        $fechaHastaInput = $request->input('fecha_hasta', today()->toDateString());
        $vendedorId = $request->input('vendedor_id');

        $fechaDesde = Carbon::parse($fechaDesdeInput)->startOfDay();
        $fechaHasta = Carbon::parse($fechaHastaInput)->endOfDay();

        $salesQuery = Sale::query()
            ->where('estado', '!=', 'anulada')
            ->whereBetween('fecha', [$fechaDesde, $fechaHasta])
            ->when($vendedorId, fn ($q) => $q->where('vendedor_id', $vendedorId));

        $totalVentas = (float) $salesQuery->sum('total');
        $cantidadVentas = (int) $salesQuery->count();
        $ticketPromedio = $cantidadVentas > 0 ? round($totalVentas / $cantidadVentas, 2) : 0.0;

        // Tasa de conversión de cotizaciones en el período
        $quotesQuery = Quote::query()->whereBetween('fecha', [$fechaDesde, $fechaHasta])
            ->when($vendedorId, fn ($q) => $q->where('vendedor_id', $vendedorId));
        $totalCotizaciones = (int) $quotesQuery->count();
        $cotizacionesGanadas = (int) (clone $quotesQuery)->whereIn('estado', ['convertida', 'aceptada'])->count();
        $tasaConversion = $totalCotizaciones > 0 ? round(($cotizacionesGanadas / $totalCotizaciones) * 100, 1) : 0.0;

        // Desglose por Vendedor
        $porVendedor = Sale::query()
            ->where('sales.estado', '!=', 'anulada')
            ->whereBetween('sales.fecha', [$fechaDesde, $fechaHasta])
            ->when($vendedorId, fn ($q) => $q->where('sales.vendedor_id', $vendedorId))
            ->join('users', 'users.id', '=', 'sales.vendedor_id')
            ->selectRaw('users.name as nombre, count(*) as cantidad, sum(sales.total) as monto')
            ->groupBy('users.id', 'users.name')
            ->orderByDesc('monto')
            ->get()
            ->map(fn ($r) => [
                'nombre' => (string) $r->nombre,
                'cantidad' => (int) $r->cantidad,
                'monto' => round((float) $r->monto, 2),
            ])
            ->all();

        // Top Clientes
        $topClientes = Sale::query()
            ->where('sales.estado', '!=', 'anulada')
            ->whereBetween('sales.fecha', [$fechaDesde, $fechaHasta])
            ->when($vendedorId, fn ($q) => $q->where('sales.vendedor_id', $vendedorId))
            ->join('clients', 'clients.id', '=', 'sales.client_id')
            ->selectRaw('clients.razon_social as cliente, count(*) as cantidad, sum(sales.total) as total')
            ->groupBy('clients.id', 'clients.razon_social')
            ->orderByDesc('total')
            ->limit(10)
            ->get()
            ->map(fn ($r) => [
                'cliente' => (string) $r->cliente,
                'cantidad' => (int) $r->cantidad,
                'total' => round((float) $r->total, 2),
            ])
            ->all();

        // Top Ítems vendidos
        $topItems = SaleItem::query()
            ->with(['product:id,nombre', 'service:id,nombre'])
            ->whereHas('sale', function ($q) use ($fechaDesde, $fechaHasta, $vendedorId) {
                $q->where('estado', '!=', 'anulada')
                    ->whereBetween('fecha', [$fechaDesde, $fechaHasta])
                    ->when($vendedorId, fn ($sq) => $sq->where('vendedor_id', $vendedorId));
            })
            ->get()
            ->groupBy(fn (SaleItem $i) => $i->product?->nombre ?? $i->service?->nombre ?? 'Ítem')
            ->map(function ($group, $nombre) {
                return [
                    'nombre' => (string) $nombre,
                    'cantidad' => (int) $group->sum('cantidad'),
                    'monto' => round((float) $group->sum('subtotal'), 2),
                ];
            })
            ->sortByDesc('monto')
            ->take(10)
            ->values()
            ->all();

        return [
            'fechaDesde' => $fechaDesde,
            'fechaHasta' => $fechaHasta,
            'totalVentas' => round($totalVentas, 2),
            'cantidadVentas' => $cantidadVentas,
            'ticketPromedio' => $ticketPromedio,
            'tasaConversion' => $tasaConversion,
            'porVendedor' => $porVendedor,
            'topClientes' => $topClientes,
            'topItems' => $topItems,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getInventarioData(Request $request): array
    {
        $sedeId = $request->input('sede_id');
        $soloBajoMinimo = $request->boolean('solo_bajo_minimo', false);

        $sedeNombre = 'Todas las sedes';
        if ($sedeId) {
            $sede = Sede::find($sedeId);
            if ($sede) {
                $sedeNombre = $sede->nombre;
            }
        }

        $productsQuery = Product::query()
            ->where('activo', true)
            ->withCount(['units as stock_disponible' => function ($q) use ($sedeId) {
                $q->where('estado', 'disponible')
                    ->when($sedeId, fn ($sq) => $sq->where('sede_id', $sedeId));
            }])
            ->orderBy('nombre');

        $allProducts = $productsQuery->get()->map(function (Product $p) {
            $disponible = (int) $p->stock_disponible;
            $minimo = $p->stock_minimo !== null ? (int) $p->stock_minimo : null;
            $bajoMinimo = $minimo !== null && $minimo > 0 && $disponible <= $minimo;
            $valorizacion = round($disponible * (float) $p->precio_venta, 2);

            return [
                'id' => $p->id,
                'codigo' => $p->codigo,
                'nombre' => $p->nombre,
                'unidad_medida' => $p->unidad_medida,
                'precio_venta' => (float) $p->precio_venta,
                'serializado' => (bool) $p->serializado,
                'stock_minimo' => $minimo,
                'stock_disponible' => $disponible,
                'bajo_minimo' => $bajoMinimo,
                'valorizacion' => $valorizacion,
            ];
        });

        $filteredProducts = $soloBajoMinimo
            ? $allProducts->filter(fn ($p) => $p['bajo_minimo'])->values()
            : $allProducts;

        $valorizacionTotal = round($allProducts->sum('valorizacion'), 2);
        $totalProductos = $allProducts->count();
        $totalUnidades = (int) $allProducts->sum('stock_disponible');
        $totalBajoMinimo = $allProducts->filter(fn ($p) => $p['bajo_minimo'])->count();

        return [
            'sedeNombre' => $sedeNombre,
            'valorizacionTotal' => $valorizacionTotal,
            'totalProductos' => $totalProductos,
            'totalUnidades' => $totalUnidades,
            'totalBajoMinimo' => $totalBajoMinimo,
            'productos' => $filteredProducts->all(),
        ];
    }
}
