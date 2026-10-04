<?php

namespace App\Http\Controllers\Almacen;

use App\Actions\Almacen\CreateStockAdjustment;
use App\Http\Controllers\Controller;
use App\Http\Requests\Almacen\StoreStockAdjustmentRequest;
use App\Models\InventoryMovement;
use App\Models\InventoryUnit;
use App\Models\Product;
use App\Models\ProductLot;
use App\Models\Sede;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StockAdjustmentController extends Controller
{
    /**
     * Listado y formulario de ajustes de inventario (§84.10).
     */
    public function index(Request $request, Team $current_team): Response
    {
        // El almacenero ve y ajusta solo su almacén; el Gerente, todos.
        $almacenId = $request->user()->almacenRestringidoId();

        $sedes = Sede::query()
            ->whereIn('tipo', ['almacen', 'mixta'])
            ->where('activo', true)
            ->when($almacenId, fn ($q) => $q->where('id', $almacenId))
            ->orderBy('nombre')
            ->with('ubicacion')->get(['id', 'nombre', 'tipo', 'ubigeo']);

        $products = Product::query()
            ->where('activo', true)
            ->orderBy('nombre')
            ->get(['id', 'codigo', 'nombre', 'serializado', 'controla_lote', 'unidad_medida']);

        // Unidades serializadas existentes en stock o de baja para el selector contextual
        $units = InventoryUnit::query()
            ->whereIn('estado', ['disponible', 'baja'])
            ->when($almacenId, fn ($q) => $q->where('sede_almacen_id', $almacenId))
            ->with(['product:id,codigo,nombre', 'sedeAlmacen:id,nombre'])
            ->orderBy('numero_serie')
            ->get(['id', 'product_id', 'sede_almacen_id', 'numero_serie', 'marca', 'estado']);

        // Lotes con saldo, para dar de baja uno puntual (por ejemplo, vencido).
        $lotes = ProductLot::query()
            ->when($almacenId, fn ($q) => $q->where('sede_id', $almacenId))
            ->withSum('movements as saldo', 'cantidad')
            ->orderBy('fecha_vencimiento')
            ->get(['id', 'product_id', 'sede_id', 'lote', 'fecha_vencimiento'])
            ->filter(fn (ProductLot $lote) => (int) $lote->getAttribute('saldo') > 0)
            ->map(fn (ProductLot $lote) => [
                'id' => $lote->id,
                'product_id' => $lote->product_id,
                'sede_id' => $lote->sede_id,
                'lote' => $lote->lote,
                'fecha_vencimiento' => $lote->fecha_vencimiento?->toDateString(),
                'vencido' => $lote->estaVencido(),
                'saldo' => (int) $lote->getAttribute('saldo'),
            ])
            ->values();

        // Historial de ajustes del Kardex
        $ajustes = InventoryMovement::query()
            ->where('tipo', 'ajuste')
            ->when($almacenId, fn ($q) => $q->where('sede_id', $almacenId))
            ->with([
                'product:id,codigo,nombre,unidad_medida,serializado',
                'sede:id,nombre',
                'user:id,name',
                'inventoryUnit:id,numero_serie,marca,estado',
                'lot:id,lote,fecha_vencimiento',
            ])
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('almacen/ajustes/index', [
            'ajustes' => $ajustes,
            'sedes' => $sedes,
            'products' => $products,
            'units' => $units,
            'lotes' => $lotes,
            'kpis' => [
                'total_ajustes' => InventoryMovement::where('tipo', 'ajuste')->when($almacenId, fn ($q) => $q->where('sede_id', $almacenId))->count(),
                'ajustes_mes' => InventoryMovement::where('tipo', 'ajuste')
                    ->when($almacenId, fn ($q) => $q->where('sede_id', $almacenId))
                    ->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])
                    ->count(),
                'unidades_dadas_de_baja' => InventoryUnit::where('estado', 'baja')->when($almacenId, fn ($q) => $q->where('sede_almacen_id', $almacenId))->count(),
            ],
        ]);
    }

    /**
     * Aplica el nuevo ajuste de stock directo (§84.10).
     */
    public function store(
        Team $current_team,
        StoreStockAdjustmentRequest $request,
        CreateStockAdjustment $createStockAdjustment
    ): RedirectResponse {
        $movement = $createStockAdjustment->handle($request->validated(), $request->user());

        $tipoTexto = $movement->cantidad > 0 ? 'incremento' : 'decremento';
        // Con lotes, una baja puede repartirse en varios movimientos.
        $cantidadAbs = (int) $request->validated('cantidad');

        return redirect()->route('almacen.ajustes.index', ['current_team' => $current_team])
            ->with('success', "Ajuste de {$tipoTexto} por {$cantidadAbs} unidades registrado correctamente en el Kardex.");
    }
}
