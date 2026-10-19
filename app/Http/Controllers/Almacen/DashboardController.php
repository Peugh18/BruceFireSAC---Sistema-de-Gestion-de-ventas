<?php

namespace App\Http\Controllers\Almacen;

use App\Http\Controllers\Controller;
use App\Models\Equipment;
use App\Models\InventoryMovement;
use App\Models\InventoryTransfer;
use App\Models\InventoryUnit;
use App\Models\Product;
use App\Models\ProductLot;
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

        $recepcionesDeHoy = Reception::query()
            ->whereDate('created_at', today())
            ->when($almacenId, fn ($q) => $q->where('sede_almacen_id', $almacenId))
            ->with('sedeAlmacen:id,nombre')
            ->latest('id')
            ->limit(5)
            ->get()
            ->map(fn (Reception $reception) => [
                'id' => $reception->id,
                'proveedor' => $reception->proveedor,
                'documento_referencia' => $reception->documento_referencia,
                'sede' => $reception->sedeAlmacen->nombre,
            ])
            ->all();

        $trasladosPendientes = InventoryTransfer::query()
            ->whereIn('estado', [InventoryTransfer::PENDIENTE_DESPACHO, InventoryTransfer::EN_TRANSITO])
            ->when($almacenId, fn ($query) => $query->where(fn ($subquery) => $subquery
                ->where('origen_sede_id', $almacenId)
                ->orWhere('destino_sede_id', $almacenId)))
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
                    'sede' => $movement->sede->nombre,
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

        // 5. Lotes vencidos o que vencen en los próximos 60 días con stock:
        // los vencidos se dan de baja (Ajustes) y los próximos se venden antes.
        $lotesPorVencer = ProductLot::query()
            ->when($almacenId, fn ($q) => $q->where('sede_id', $almacenId))
            ->whereNotNull('fecha_vencimiento')
            ->whereDate('fecha_vencimiento', '<=', today()->addDays(ProductLot::DIAS_AVISO))
            ->with(['product:id,codigo,nombre,unidad_medida', 'sede:id,nombre'])
            ->withSum('movements as saldo', 'cantidad')
            ->orderBy('fecha_vencimiento')
            ->get()
            ->filter(fn (ProductLot $lote) => (int) $lote->getAttribute('saldo') > 0)
            ->map(fn (ProductLot $lote) => [
                'id' => $lote->id,
                'producto' => $lote->product->nombre,
                'unidad_medida' => $lote->product->unidad_medida,
                'lote' => $lote->lote,
                'sede' => $lote->sede->nombre,
                'fecha_vencimiento' => $lote->fecha_vencimiento?->toDateString(),
                'vencido' => $lote->estaVencido(),
                'saldo' => (int) $lote->getAttribute('saldo'),
            ])
            ->values()
            ->all();

        return Inertia::render('almacen/dashboard', [
            'graficos' => $this->graficos($almacenId, $productosBajoMinimo),
            'lotes_por_vencer' => $lotesPorVencer,
            'stock' => [
                'total_disponible' => $unidadesDisponiblesTotal,
                'por_sede' => $stockPorSede,
            ],
            'recepciones_hoy' => $recepcionesHoy,
            'recepciones_de_hoy' => $recepcionesDeHoy,
            'traslados_pendientes' => $trasladosPendientes,
            'movimientos_recientes' => $movimientosRecientes,
            'productos_bajo_minimo' => $productosBajoMinimo,
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $bajoMinimo
     * @return array<string, mixed>
     */
    private function graficos(?int $almacenId, array $bajoMinimo): array
    {
        $productos = Product::query()->where('activo', true)
            ->conStock($almacenId ? [$almacenId] : null)
            ->with('categoriaDelCatalogo')->get();
        $estados = InventoryUnit::query()
            ->when($almacenId, fn ($query) => $query->where('sede_almacen_id', $almacenId))
            ->whereHas('product', fn ($query) => $query->where('categoria', 'extintor')->orWhereNotNull('agente'))
            ->selectRaw('estado, COUNT(*) as total')->groupBy('estado')->pluck('total', 'estado');
        $enTaller = Equipment::query()->whereHas('serviceOrders', fn ($query) => $query
            ->whereIn('service_orders.estado', ['recibido_planta', 'en_proceso', 'autorizado', 'esperando_autorizacion'])
            ->where(fn ($area) => $area->where('departamento_tecnico', 'planta')->orWhereNull('departamento_tecnico'))
            ->when($almacenId, fn ($sede) => $sede->whereIn('service_orders.sede_id', Sede::idsComercialesAtendidasPor($almacenId))))->count();
        $inicio = today()->startOfWeek()->subWeeks(7);
        $movimientos = InventoryMovement::query()
            ->when($almacenId, fn ($query) => $query->where('sede_id', $almacenId))
            ->whereBetween('created_at', [$inicio, now()])
            ->selectRaw('DATE(created_at) as dia, SUM(CASE WHEN cantidad > 0 THEN cantidad ELSE 0 END) as entradas, SUM(CASE WHEN cantidad < 0 THEN -cantidad ELSE 0 END) as salidas')
            ->groupByRaw('DATE(created_at)')->get();

        return [
            'categorias' => $productos->groupBy(fn (Product $producto) => $producto->categoriaDelCatalogo->nombre ?? 'Sin categoría')
                ->map(fn ($filas, string $nombre) => ['nombre' => $nombre, 'valor' => (int) $filas->sum(fn (Product $producto) => max(0, $producto->stockDisponible()))])->values(),
            'minimos' => collect($bajoMinimo)->take(8)->map(fn (array $producto) => ['nombre' => $producto['codigo'], 'valor' => max(0, $producto['stock_disponible'])])->values(),
            'estados' => collect(['disponible' => 'Disponible', 'reservado' => 'Reservado', 'en_taller' => 'En taller'])
                ->map(fn (string $nombre, string $estado) => ['nombre' => $nombre, 'valor' => $estado === 'en_taller' ? $enTaller : (int) $estados->get($estado, 0)])->values(),
            'movimientos' => collect(range(0, 7))->map(function (int $semana) use ($inicio, $movimientos): array {
                $desde = $inicio->copy()->addWeeks($semana);
                $hasta = $desde->copy()->endOfWeek()->toDateString();
                $filas = $movimientos->filter(fn (InventoryMovement $fila) => $fila->getAttribute('dia') >= $desde->toDateString() && $fila->getAttribute('dia') <= $hasta);

                return ['nombre' => $desde->format('d/m'), 'valor' => (int) $filas->sum('entradas'), 'secundario' => (int) $filas->sum('salidas')];
            }),
        ];
    }
}
