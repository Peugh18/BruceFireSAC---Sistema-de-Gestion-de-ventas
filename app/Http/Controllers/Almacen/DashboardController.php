<?php

namespace App\Http\Controllers\Almacen;

use App\Http\Controllers\Controller;
use App\Models\InventoryMovement;
use App\Models\InventoryUnit;
use App\Models\Product;
use App\Models\Reception;
use App\Models\Sede;
use App\Models\Team;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Dashboard de Almacén: KPIs operativos propios del rol.
     * Conforme a §84.5 del Documento Maestro:
     * - Unidades disponibles en stock (total y agrupable por sede).
     * - Recepciones de hoy (movimientos de tipo ingreso en la fecha actual).
     * - Movimientos recientes: últimos 10 InventoryMovement.
     * - Productos bajo el mínimo (reabastecimiento de almacén según products.stock_minimo).
     */
    public function __invoke(Team $current_team, Request $request): Response
    {
        // El almacenero ve su almacén; el Gerente, todos.
        $almacenId = $request->user()->almacenRestringidoId();
        $almacenes = $almacenId ? [$almacenId] : null;

        // 1. Unidades disponibles en stock
        $unidadesDisponiblesTotal = InventoryUnit::query()
            ->where('estado', 'disponible')
            ->when($almacenId, fn ($q) => $q->where('sede_almacen_id', $almacenId))
            ->count();

        // Stock disponible agrupado por sede de almacén
        $sedesAlmacen = Sede::query()
            ->whereIn('tipo', ['almacen', 'mixta'])
            ->where('activo', true)
            ->when($almacenId, fn ($q) => $q->where('id', $almacenId))
            ->get();

        // El conteo real por sede se calcula directamente de InventoryUnit
        $stockPorSedeCounts = InventoryUnit::query()
            ->where('estado', 'disponible')
            ->selectRaw('sede_almacen_id, count(*) as total')
            ->groupBy('sede_almacen_id')
            ->pluck('total', 'sede_almacen_id');

        $stockPorSede = $sedesAlmacen->map(function (Sede $sede) use ($stockPorSedeCounts) {
            return [
                'sede_id' => $sede->id,
                'nombre' => $sede->nombre,
                'tipo' => $sede->tipo,
                'ciudad' => $sede->ciudad,
                'unidades_disponibles' => (int) ($stockPorSedeCounts[$sede->id] ?? 0),
            ];
        })->values()->all();

        // 2. Recepciones de hoy: documentos de proveedor, no movimientos (una
        // recepción de 10 extintores es una sola, y una anulación de venta
        // no es una recepción).
        $recepcionesHoy = Reception::query()
            ->whereDate('created_at', today())
            ->when($almacenId, fn ($q) => $q->where('sede_almacen_id', $almacenId))
            ->count();

        // 3. Movimientos recientes (últimos 10)
        $movimientosRecientes = InventoryMovement::query()
            ->when($almacenId, fn ($q) => $q->where('sede_id', $almacenId))
            ->with([
                'product:id,codigo,nombre,unidad_medida,serializado',
                'inventoryUnit:id,numero_serie,estado',
                'sede:id,nombre',
                'user:id,name',
            ])
            ->latest('id')
            ->limit(10)
            ->get()
            ->map(function (InventoryMovement $movement) {
                return [
                    'id' => $movement->id,
                    'fecha' => $movement->created_at?->toIso8601String(),
                    'tipo' => $movement->tipo,
                    'cantidad' => $movement->cantidad,
                    'producto' => [
                        'id' => $movement->product->id,
                        'codigo' => $movement->product->codigo,
                        'nombre' => $movement->product->nombre,
                        'unidad_medida' => $movement->product->unidad_medida,
                        'serializado' => $movement->product->serializado,
                    ],
                    'unidad_serie' => $movement->inventoryUnit?->numero_serie,
                    'sede' => $movement->sede?->nombre,
                    'usuario' => $movement->user?->name,
                    'observacion' => $movement->observacion,
                ];
            })
            ->all();

        // 4. Productos bajo el mínimo (reabastecimiento propio del almacén)
        // Solo aplica a productos activos con stock_minimo definido (> 0)
        // Con serie se cuentan unidades; sin serie (repuestos, EPP), el Kardex.
        $productosBajoMinimo = Product::query()
            ->conStock($almacenes)
            ->bajoMinimo($almacenes)
            ->get()
            ->sortBy(fn (Product $product) => $product->stockDisponible())
            ->map(function (Product $product) {
                return [
                    'id' => $product->id,
                    'codigo' => $product->codigo,
                    'nombre' => $product->nombre,
                    'unidad_medida' => $product->unidad_medida,
                    'stock_minimo' => $product->stock_minimo,
                    'stock_disponible' => $product->stockDisponible(),
                    'diferencia' => (int) $product->stock_minimo - $product->stockDisponible(),
                ];
            })
            ->values()
            ->all();

        return Inertia::render('almacen/dashboard', [
            'stock' => [
                'total_disponible' => $unidadesDisponiblesTotal,
                'por_sede' => $stockPorSede,
            ],
            'recepciones_hoy' => $recepcionesHoy,
            'movimientos_recientes' => $movimientosRecientes,
            'productos_bajo_minimo' => $productosBajoMinimo,
        ]);
    }
}
