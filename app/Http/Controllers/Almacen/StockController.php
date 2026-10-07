<?php

namespace App\Http\Controllers\Almacen;

use App\Http\Controllers\Controller;
use App\Models\InventoryMovement;
use App\Models\InventoryUnit;
use App\Models\Product;
use App\Models\ProductLot;
use App\Models\Sede;
use App\Models\Team;
use Illuminate\Http\Request;
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
            ->with('ubicacion')->get(['id', 'nombre', 'tipo', 'ubigeo']);

        // Los productos se paginan en la base de datos; el stock por sede se
        // calcula solo para los de esta página.
        $paginaProductos = Product::query()
            ->where('activo', true)
            ->when(! in_array($tipo, ['', 'todos', 'producto'], true), fn ($q) => $q->whereRaw('1 = 0'))
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('codigo', 'like', "%{$search}%")
                        ->orWhere('codigo_barras', $search)
                        ->orWhere('nombre', 'like', "%{$search}%");
                });
            })
            ->orderBy('nombre')
            ->paginate(15)
            ->withQueryString();

        $ids = $paginaProductos->getCollection()->pluck('id')->all();

        // Con serie: unidades 'disponible' por sede. Sin serie (§84.11): saldo del Kardex.
        $stockMatrix = [];
        InventoryUnit::query()
            ->where('estado', 'disponible')
            ->whereIn('product_id', $ids)
            ->selectRaw('product_id, sede_almacen_id as sede_id, count(*) as total')
            ->groupBy('product_id', 'sede_almacen_id')
            ->get()
            ->each(function ($fila) use (&$stockMatrix) {
                $stockMatrix[$fila->product_id][$fila->sede_id] = (int) $fila->total;
            });
        InventoryMovement::query()
            ->join('products', 'inventory_movements.product_id', '=', 'products.id')
            ->where('products.serializado', false)
            ->whereIn('inventory_movements.product_id', $ids)
            ->selectRaw('inventory_movements.product_id, inventory_movements.sede_id, sum(inventory_movements.cantidad) as total')
            ->groupBy('inventory_movements.product_id', 'inventory_movements.sede_id')
            ->get()
            ->each(function ($fila) use (&$stockMatrix) {
                $stockMatrix[$fila->product_id][$fila->sede_id] = (int) $fila->total;
            });

        // Lotes con saldo de los productos de esta página que llevan lote.
        $lotes = ProductLot::query()
            ->whereIn('product_id', $paginaProductos->getCollection()->where('controla_lote', true)->pluck('id')->all())
            ->when($almacenId, fn ($q) => $q->where('sede_id', $almacenId))
            ->with('sede:id,nombre')
            ->withSum('movements as saldo', 'cantidad')
            ->orderBy('fecha_vencimiento')
            ->get()
            ->filter(fn (ProductLot $lote) => (int) $lote->getAttribute('saldo') > 0)
            ->groupBy('product_id');

        $items = $paginaProductos->through(function (Product $product) use ($sedes, $stockMatrix, $lotes) {
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
                'controla_lote' => $product->controla_lote,
                'stock_minimo' => $product->stock_minimo,
                'stock_disponible_total' => $totalDisponible,
                'stock_por_sede' => $stockPorSede,
                'lotes' => ($lotes->get($product->id) ?? collect())->map(fn (ProductLot $lote) => [
                    'lote' => $lote->lote,
                    'sede' => $lote->sede->nombre,
                    'fecha_vencimiento' => $lote->fecha_vencimiento?->toDateString(),
                    'vencido' => $lote->estaVencido(),
                    'por_vencer' => ! $lote->estaVencido() && $lote->fecha_vencimiento !== null && $lote->fecha_vencimiento->lte(today()->addDays(ProductLot::DIAS_AVISO)),
                    'saldo' => (int) $lote->getAttribute('saldo'),
                ])->values()->all(),
            ];
        });

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
                'unidades_en_stock' => InventoryUnit::where('estado', 'disponible')->when($almacenId, fn ($q) => $q->where('sede_almacen_id', $almacenId))->count(),
                // Con serie por unidades; sin serie (repuestos, EPP) por Kardex.
                'bajo_minimo' => Product::query()->bajoMinimo($almacenId ? [$almacenId] : null)->count(),
            ],
        ]);
    }
}
