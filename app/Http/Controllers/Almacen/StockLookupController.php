<?php

namespace App\Http\Controllers\Almacen;

use App\Http\Controllers\Controller;
use App\Models\InventoryMovement;
use App\Models\InventoryUnit;
use App\Models\Product;
use App\Models\Sede;
use App\Models\Team;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StockLookupController extends Controller
{
    /**
     * Pantalla de consulta rápida por serie / código de barras (§84.12).
     * 100% solo lectura, auditoría completa (incluye unidades disponibles, vendidas, reservadas y de baja).
     */
    public function index(Request $request, Team $current_team): Response
    {
        $search = trim((string) $request->input('search', ''));
        $almacenId = $request->user()->almacenRestringidoId();
        $sedeId = $almacenId ?? ($request->filled('sede_id') ? (int) $request->input('sede_id') : null);

        $sedes = Sede::query()
            ->whereIn('tipo', ['almacen', 'mixta'])
            ->where('activo', true)
            ->when($almacenId, fn ($q) => $q->where('id', $almacenId))
            ->orderBy('nombre')
            ->with('ubicacion')->get(['id', 'nombre', 'tipo', 'ubigeo']);

        $unitResult = null;
        $productResult = null;

        if ($search !== '') {
            // 1. Buscar primero unidad serializada por numero_serie (exacto o prefijo)
            $unit = InventoryUnit::query()
                ->where(function ($q) use ($search) {
                    $q->where('numero_serie', $search)
                        ->orWhere('numero_serie', 'like', "%{$search}%");
                })
                ->when($sedeId, function ($q) use ($sedeId) {
                    $q->where('sede_almacen_id', $sedeId);
                })
                ->with([
                    'product:id,codigo,nombre,unidad_medida,serializado',
                    'sedeAlmacen:id,nombre,tipo,ubigeo', 'sedeAlmacen.ubicacion',
                ])
                ->first();

            if ($unit) {
                // Cargar los últimos 5 movimientos de Kardex para auditoría de esta unidad
                $ultimosMovimientos = InventoryMovement::query()
                    ->where('inventory_unit_id', $unit->id)
                    ->with(['sede:id,nombre', 'user:id,name'])
                    ->latest('id')
                    ->limit(5)
                    ->get()
                    ->map(fn (InventoryMovement $m) => [
                        'id' => $m->id,
                        'tipo' => $m->tipo,
                        'cantidad' => $m->cantidad,
                        'fecha' => $m->created_at->toIso8601String(),
                        'sede' => $m->sede->nombre,
                        'usuario' => $m->user?->name ?? 'Sistema',
                        'observacion' => $m->observacion,
                    ]);

                $unitResult = [
                    'id' => $unit->id,
                    'numero_serie' => $unit->numero_serie,
                    'estado' => $unit->estado,
                    'marca' => $unit->marca,
                    'anio_fabricacion' => $unit->anio_fabricacion,
                    'fecha_ingreso' => $unit->fecha_ingreso?->toDateString(),
                    'sede' => $unit->sedeAlmacen,
                    'producto' => $unit->product,
                    'movimientos' => $ultimosMovimientos,
                ];
            } else {
                // 2. Si no es serie de unidad, buscar si coincide con un producto
                // El código de barras del fabricante (EPP) o el código interno
                // exactos tienen prioridad sobre un nombre parecido.
                $product = Product::query()
                    ->where('codigo', $search)
                    ->orWhere('codigo_barras', $search)
                    ->orWhere('nombre', 'like', "%{$search}%")
                    ->orderByRaw('(codigo = ? or codigo_barras = ?) desc', [$search, $search])
                    ->first();

                if ($product) {
                    $stockPorSede = [];
                    $totalStock = 0;

                    // Una sola consulta agrupada por sede (sin una por almacén).
                    $porSede = $product->serializado
                        ? InventoryUnit::query()
                            ->where('product_id', $product->id)
                            ->where('estado', 'disponible')
                            ->whereIn('sede_almacen_id', $sedes->pluck('id'))
                            ->selectRaw('sede_almacen_id as sede_id, count(*) as total')
                            ->groupBy('sede_almacen_id')
                            ->pluck('total', 'sede_id')
                        : InventoryMovement::query()
                            ->where('product_id', $product->id)
                            ->whereIn('sede_id', $sedes->pluck('id'))
                            ->selectRaw('sede_id, sum(cantidad) as total')
                            ->groupBy('sede_id')
                            ->pluck('total', 'sede_id');

                    foreach ($sedes as $s) {
                        $qty = (int) ($porSede[$s->id] ?? 0);

                        $stockPorSede[$s->id] = $qty;
                        $totalStock += $qty;
                    }

                    $ultimosMovimientos = InventoryMovement::query()
                        ->where('product_id', $product->id)
                        ->with(['sede:id,nombre', 'user:id,name', 'inventoryUnit:id,numero_serie'])
                        ->latest('id')
                        ->limit(5)
                        ->get()
                        ->map(fn (InventoryMovement $m) => [
                            'id' => $m->id,
                            'tipo' => $m->tipo,
                            'cantidad' => $m->cantidad,
                            'fecha' => $m->created_at->toIso8601String(),
                            'sede' => $m->sede->nombre,
                            'usuario' => $m->user?->name ?? 'Sistema',
                            'observacion' => $m->observacion,
                            'numero_serie' => $m->inventoryUnit?->numero_serie,
                        ]);

                    $productResult = [
                        'id' => $product->id,
                        'codigo' => $product->codigo,
                        'nombre' => $product->nombre,
                        'serializado' => $product->serializado,
                        'unidad_medida' => $product->unidad_medida,
                        'stock_total' => $totalStock,
                        'stock_por_sede' => $stockPorSede,
                        'movimientos' => $ultimosMovimientos,
                    ];
                }
            }
        }

        return Inertia::render('almacen/consulta/index', [
            'sedes' => $sedes,
            'filters' => [
                'search' => $search,
                'sede_id' => $sedeId,
            ],
            'unitResult' => $unitResult,
            'productResult' => $productResult,
        ]);
    }

    /**
     * Endpoint API JSON para autocompletar / búsqueda asíncrona rápida (§84.12).
     */
    public function search(Request $request): JsonResponse
    {
        $q = trim((string) $request->input('q', ''));
        if (strlen($q) < 2) {
            return response()->json([]);
        }

        $units = InventoryUnit::query()
            ->where('numero_serie', 'like', "%{$q}%")
            ->with(['product:id,codigo,nombre', 'sedeAlmacen:id,nombre'])
            ->limit(10)
            ->get()
            ->map(fn (InventoryUnit $u) => [
                'id' => $u->id,
                'numero_serie' => $u->numero_serie,
                'estado' => $u->estado,
                'producto' => $u->product->nombre,
                'sede' => $u->sedeAlmacen->nombre,
            ]);

        return response()->json($units);
    }
}
