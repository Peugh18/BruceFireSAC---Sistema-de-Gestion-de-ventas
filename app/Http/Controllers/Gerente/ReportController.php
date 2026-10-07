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
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
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
        $data = $this->getInventarioData($request, paginar: false);

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

        // Una fecha mal escrita en la dirección no rompe el reporte: se usa
        // el mes en curso.
        $fecha = fn (mixed $valor, CarbonInterface $porDefecto): CarbonInterface => is_string($valor) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor) === 1 && checkdate((int) substr($valor, 5, 2), (int) substr($valor, 8, 2), (int) substr($valor, 0, 4))
            ? Carbon::createFromFormat('Y-m-d', $valor)
            : $porDefecto;
        $fechaDesde = $fecha($fechaDesdeInput, now()->startOfMonth())->startOfDay();
        $fechaHasta = $fecha($fechaHastaInput, today())->endOfDay();

        $salesQuery = Sale::query()
            ->where('estado', 'confirmada')
            ->whereBetween('fecha', [$fechaDesde, $fechaHasta])
            ->when($vendedorId, fn ($q) => $q->where('vendedor_id', $vendedorId));

        $totalVentas = (float) $salesQuery->sum('total');
        $cantidadVentas = (int) $salesQuery->count();
        $ticketPromedio = $cantidadVentas > 0 ? round($totalVentas / $cantidadVentas, 2) : 0.0;

        // Tasa de conversión de cotizaciones en el período
        // Las cotizaciones en borrador o anuladas no se ofrecieron al cliente.
        $quotesQuery = Quote::query()->whereBetween('fecha', [$fechaDesde, $fechaHasta])
            ->whereNotIn('estado', ['borrador', 'anulada'])
            ->when($vendedorId, fn ($q) => $q->where('vendedor_id', $vendedorId));
        $totalCotizaciones = (int) $quotesQuery->count();
        $cotizacionesGanadas = (int) (clone $quotesQuery)->whereIn('estado', ['convertida', 'aceptada'])->count();
        $tasaConversion = $totalCotizaciones > 0 ? round(($cotizacionesGanadas / $totalCotizaciones) * 100, 1) : 0.0;

        // Desglose por Vendedor
        $porVendedor = Sale::query()
            ->where('sales.estado', 'confirmada')
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
            ->where('sales.estado', 'confirmada')
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
                $q->where('estado', 'confirmada')
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
     * Inventario valorizado al COSTO promedio de compra (no al precio de
     * venta). Los totales salen de la base de datos; la lista se pagina
     * ahí también (el PDF pide todo el listado).
     *
     * @return array<string, mixed>
     */
    protected function getInventarioData(Request $request, bool $paginar = true): array
    {
        $sedeId = $request->input('sede_id');
        $soloBajoMinimo = $request->boolean('solo_bajo_minimo', false);

        $sedeNombre = 'Todas las sedes';
        $almacenes = null;
        if ($sedeId) {
            $sede = Sede::query()->find((int) $sedeId);
            if ($sede) {
                $sedeNombre = $sede->nombre;
                // Una tienda vende del stock de su almacén.
                $almacenes = [$sede->almacenEfectivoId()];
            }
        }

        $disponible = 'CASE WHEN p.serializado = 1 THEN p.stock_unidades ELSE p.stock_kardex END';
        $totales = DB::query()
            ->fromSub(Product::query()->where('activo', true)->conStock($almacenes), 'p')
            ->selectRaw("COUNT(*) as productos,
                COALESCE(SUM({$disponible}), 0) as unidades,
                COALESCE(SUM(({$disponible}) * COALESCE(p.costo_promedio, 0)), 0) as valor,
                COALESCE(SUM(CASE WHEN p.costo_promedio IS NULL AND ({$disponible}) > 0 THEN 1 ELSE 0 END), 0) as sin_costo,
                COALESCE(SUM(CASE WHEN p.stock_minimo > 0 AND ({$disponible}) <= p.stock_minimo THEN 1 ELSE 0 END), 0) as bajo_minimo")
            ->first();

        $lista = Product::query()
            ->where('activo', true)
            ->conStock($almacenes)
            ->when($soloBajoMinimo, fn ($q) => $q->bajoMinimo($almacenes))
            ->orderBy('nombre');

        $paginador = $paginar ? $lista->paginate(25)->withQueryString() : null;
        $filas = $paginador !== null ? collect($paginador->items()) : $lista->get();
        $productos = $filas->map(function (Product $p) {
            $stock = $p->stockDisponible();
            $minimo = $p->stock_minimo !== null ? (int) $p->stock_minimo : null;
            $costo = $p->costo_promedio !== null ? (float) $p->costo_promedio : null;

            return [
                'id' => $p->id,
                'codigo' => $p->codigo,
                'nombre' => $p->nombre,
                'unidad_medida' => $p->unidad_medida,
                'precio_venta' => (float) $p->precio_venta,
                'costo_promedio' => $costo,
                'sin_costo' => $costo === null && $stock > 0,
                'serializado' => (bool) $p->serializado,
                'stock_minimo' => $minimo,
                'stock_disponible' => $stock,
                'bajo_minimo' => $minimo !== null && $minimo > 0 && $stock <= $minimo,
                'valorizacion' => round($stock * ($costo ?? 0), 2),
            ];
        })->values()->all();

        return [
            'sedeNombre' => $sedeNombre,
            'valorizacionTotal' => round((float) $totales->valor, 2),
            'totalProductos' => (int) $totales->productos,
            'totalUnidades' => (int) $totales->unidades,
            'totalBajoMinimo' => (int) $totales->bajo_minimo,
            'productosSinCosto' => (int) $totales->sin_costo,
            'productos' => $productos,
            'paginacion' => $paginador !== null ? [
                'pagina' => $paginador->currentPage(),
                'ultima' => $paginador->lastPage(),
                'total' => $paginador->total(),
                'anterior' => $paginador->previousPageUrl(),
                'siguiente' => $paginador->nextPageUrl(),
            ] : null,
        ];
    }
}
