<?php

namespace App\Http\Controllers\Gerente;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Sede;
use App\Models\Team;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    /** La página de reportes se redirige al dashboard mensual. */
    public function index(Team $current_team, Request $request): RedirectResponse
    {
        return redirect()->route('gerente.dashboard', array_filter([
            'current_team' => $current_team,
            'mes' => $request->input('mes'),
            'sede' => $request->integer('sede') ?: null,
        ]));
    }

    public function exportComercialPdf(Team $current_team, Request $request): HttpResponse
    {
        $data = (new DashboardController)->datosMensuales($request);
        $pdf = Pdf::loadView('pdf.reporte-comercial', $data)->setPaper('a4');

        return $pdf->download('reporte-mensual-'.$data['mes'].'-'.str($data['sedeNombre'])->slug().'.pdf');
    }

    public function exportInventarioPdf(Team $current_team, Request $request): HttpResponse
    {
        $data = self::inventarioResumen($request->integer('sede_id') ?: null);
        $pdf = Pdf::loadView('pdf.reporte-inventario', $data)->setPaper('a4');

        return $pdf->download('reporte-inventario-'.today()->toDateString().'.pdf');
    }

    /**
     * Inventario valorizado al costo promedio de compra.
     *
     * @return array<string, mixed>
     */
    public static function inventarioResumen(?int $sedeId = null): array
    {
        $sedeNombre = 'Todas las sedes';
        $almacenes = null;
        if ($sedeId) {
            $sede = Sede::query()->find($sedeId);
            if ($sede) {
                $sedeNombre = $sede->nombre;
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

        $productos = Product::query()
            ->where('activo', true)
            ->conStock($almacenes)
            ->orderBy('nombre')
            ->get()
            ->map(function (Product $producto): array {
                $stock = $producto->stockDisponible();
                $minimo = $producto->stock_minimo !== null ? (int) $producto->stock_minimo : null;
                $costo = $producto->costo_promedio !== null ? (float) $producto->costo_promedio : null;

                return [
                    'id' => $producto->id,
                    'codigo' => $producto->codigo,
                    'nombre' => $producto->nombre,
                    'unidad_medida' => $producto->unidad_medida,
                    'precio_venta' => (float) $producto->precio_venta,
                    'costo_promedio' => $costo,
                    'sin_costo' => $costo === null && $stock > 0,
                    'serializado' => (bool) $producto->serializado,
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
            'paginacion' => [
                'pagina' => 1,
                'ultima' => 1,
                'total' => count($productos),
                'anterior' => null,
                'siguiente' => null,
            ],
        ];
    }
}
