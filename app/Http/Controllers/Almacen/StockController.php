<?php

namespace App\Http\Controllers\Almacen;

use App\Http\Controllers\Controller;
use App\Models\InventoryMovement;
use App\Models\InventoryUnit;
use App\Models\Product;
use App\Models\Sede;
use App\Models\Team;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Inertia\Inertia;
use Inertia\Response;

class StockController extends Controller
{
    /**
     * Módulo Stock y Kardex (Fase 3 / §84.7):
     * Listado unificado de Producto / Servicio con existencias por sede y Kardex filtrable.
     */
    public function index(Team $current_team, Request $request): Response
    {
        $search = $request->string('search')->toString();
        $tipo = $request->string('tipo')->toString();

        // 1. Sedes activas de tipo almacén o mixta
        $almacenId = $request->user()->almacenRestringidoId();

        $sedes = Sede::query()
            ->whereIn('tipo', ['almacen', 'mixta'])
            ->where('activo', true)
            ->when($almacenId, fn ($q) => $q->where('id', $almacenId))
            ->orderBy('id')
            ->get(['id', 'nombre', 'tipo', 'ciudad']);

        // 2a. Para productos serializados: conteo de InventoryUnit en estado 'disponible'
        $stockUnits = InventoryUnit::query()
            ->where('estado', 'disponible')
            ->selectRaw('product_id, sede_almacen_id as sede_id, count(*) as total')
            ->groupBy('product_id', 'sede_almacen_id')
            ->get();

        // 2b. Para productos no serializados (repuestos / componentes a granel, §84.11): saldo de Kardex
        $bulkMovements = InventoryMovement::query()
            ->join('products', 'inventory_movements.product_id', '=', 'products.id')
            ->where('products.serializado', false)
            ->selectRaw('inventory_movements.product_id, inventory_movements.sede_id, sum(inventory_movements.cantidad) as total')
            ->groupBy('inventory_movements.product_id', 'inventory_movements.sede_id')
            ->get();

        $stockMatrix = [];
        foreach ($stockUnits as $unit) {
            $stockMatrix[$unit->product_id][$unit->sede_id] = (int) $unit->total;
        }
        foreach ($bulkMovements as $bm) {
            $stockMatrix[$bm->product_id][$bm->sede_id] = max(0, (int) $bm->total);
        }

        // 3. Obtener Productos
        $products = collect();
        if ($tipo === '' || $tipo === 'todos' || $tipo === 'producto') {
            $products = Product::query()
                ->when($search !== '', function ($query) use ($search) {
                    $query->where(function ($q) use ($search) {
                        $q->where('codigo', 'like', "%{$search}%")
                            ->orWhere('nombre', 'like', "%{$search}%");
                    });
                })
                ->where('activo', true)
                ->orderBy('nombre')
                ->get()
                ->map(function (Product $product) use ($sedes, $stockMatrix) {
                    $stockPorSede = [];
                    $totalDisponible = 0;

                    foreach ($sedes as $sede) {
                        $qty = $stockMatrix[$product->id][$sede->id] ?? 0;
                        $stockPorSede[$sede->id] = $qty;
                        $totalDisponible += $qty;
                    }

                    return [
                        'id' => $product->id,
                        'tipo' => 'producto',
                        'codigo' => $product->codigo,
                        'nombre' => $product->nombre,
                        'unidad_medida' => $product->unidad_medida,
                        'precio_venta' => (float) $product->precio_venta,
                        'serializado' => $product->serializado,
                        'stock_minimo' => $product->stock_minimo,
                        'stock_disponible_total' => $totalDisponible,
                        'stock_por_sede' => $stockPorSede,
                    ];
                });
        }

        $allItems = $products->sortBy('nombre')->values()->all();

        // El catálogo se arma en PHP a partir de dos fuentes (Productos +
        // Servicios) con stock por sede ya calculado, así que se pagina el
        // arreglo resultante a mano en vez de un Eloquent::paginate().
        $itemsPerPage = 15;
        $itemsPage = Paginator::resolveCurrentPage('page') ?: 1;
        $items = new LengthAwarePaginator(
            array_slice($allItems, ($itemsPage - 1) * $itemsPerPage, $itemsPerPage),
            count($allItems),
            $itemsPerPage,
            $itemsPage,
            ['path' => $request->url(), 'query' => $request->query()],
        );

        // 6. Kardex filtrable
        $kardexProductId = $request->integer('kardex_product_id');
        $kardexSedeId = $request->integer('kardex_sede_id');
        $kardexFechaDesde = $request->string('kardex_fecha_desde')->toString();
        $kardexFechaHasta = $request->string('kardex_fecha_hasta')->toString();
        $kardexTipo = $request->string('kardex_tipo')->toString(); // 'ingreso', 'salida_venta', 'salida_servicio', 'ajuste', 'traslado'

        $kardexQuery = InventoryMovement::query()
            ->with([
                'product:id,codigo,nombre,unidad_medida,serializado',
                'inventoryUnit:id,numero_serie,estado',
                'sede:id,nombre',
                'user:id,name',
            ])
            ->when($kardexProductId > 0, fn ($q) => $q->where('product_id', $kardexProductId))
            ->when($almacenId, fn ($q) => $q->where('sede_id', $almacenId))
            ->when($kardexSedeId > 0, fn ($q) => $q->where('sede_id', $kardexSedeId))
            ->when($kardexFechaDesde !== '', fn ($q) => $q->whereDate('created_at', '>=', $kardexFechaDesde))
            ->when($kardexFechaHasta !== '', fn ($q) => $q->whereDate('created_at', '<=', $kardexFechaHasta))
            ->when(in_array($kardexTipo, ['ingreso', 'salida_venta', 'salida_servicio', 'ajuste', 'traslado'], true), fn ($q) => $q->where('tipo', $kardexTipo));

        // Paginación del Kardex
        $kardex = $kardexQuery
            ->orderByDesc('id')
            ->paginate(15, ['*'], 'kardex_page')
            ->withQueryString()
            ->through(function (InventoryMovement $mov) {
                return [
                    'id' => $mov->id,
                    'fecha' => $mov->created_at?->toIso8601String(),
                    'tipo' => $mov->tipo,
                    'cantidad' => $mov->cantidad,
                    'producto' => [
                        'id' => $mov->product->id,
                        'codigo' => $mov->product->codigo,
                        'nombre' => $mov->product->nombre,
                        'unidad_medida' => $mov->product->unidad_medida,
                        'serializado' => $mov->product->serializado,
                    ],
                    'unidad_serie' => $mov->inventoryUnit?->numero_serie,
                    'sede' => $mov->sede?->nombre,
                    'usuario' => $mov->user?->name,
                    'observacion' => $mov->observacion,
                ];
            });

        // Lista de productos para el selector de filtro del Kardex
        $productList = Product::query()
            ->where('activo', true)
            ->orderBy('nombre')
            ->get(['id', 'codigo', 'nombre']);

        return Inertia::render('almacen/stock/index', [
            'items' => $items,
            'sedes' => $sedes,
            'filters' => [
                'search' => $search,
                'tipo' => $tipo ?: 'todos',
            ],
            'kardex' => $kardex,
            'kardex_filters' => [
                'product_id' => $kardexProductId ?: null,
                'sede_id' => $kardexSedeId ?: null,
                'fecha_desde' => $kardexFechaDesde ?: null,
                'fecha_hasta' => $kardexFechaHasta ?: null,
                'tipo' => $kardexTipo ?: 'todos',
            ],
            'product_list' => $productList,
            'kpis' => [
                'total_productos' => Product::where('activo', true)->count(),
                'unidades_en_stock' => InventoryUnit::where('estado', 'disponible')->count(),
                'bajo_minimo' => Product::where('activo', true)
                    ->whereNotNull('stock_minimo')
                    ->where('stock_minimo', '>', 0)
                    ->withCount(['units as disponible' => fn ($q) => $q->where('estado', 'disponible')])
                    ->get()
                    ->filter(fn ($p) => $p->disponible <= $p->stock_minimo)
                    ->count(),
            ],
        ]);
    }
}
