<?php

namespace App\Http\Controllers\Gerente;

use App\Http\Controllers\Controller;
use App\Http\Requests\Gerente\StoreProductRequest;
use App\Http\Requests\Gerente\UpdateProductRequest;
use App\Models\Product;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    /**
     * Listado y gestión de productos físicos y repuestos (§86.4.2).
     */
    public function index(Team $current_team, Request $request): Response
    {
        $buscar = trim((string) $request->input('buscar', ''));
        $estado = (string) $request->input('estado', 'todos');
        $bajoMinimo = $request->boolean('bajo_minimo', false);

        $query = Product::query()
            ->conStock()
            ->when($buscar !== '', function ($q) use ($buscar) {
                $q->where(function ($sub) use ($buscar) {
                    $sub->where('nombre', 'like', "%{$buscar}%")
                        ->orWhere('codigo', 'like', "%{$buscar}%");
                });
            })
            ->when($estado === 'activos', fn ($q) => $q->where('activo', true))
            ->when($estado === 'inactivos', fn ($q) => $q->where('activo', false))
            ->when($bajoMinimo, fn ($q) => $q->bajoMinimo())
            ->orderBy('nombre');

        $productos = $query->paginate(15)->withQueryString()->through(function (Product $p) {
            return [
                'id' => $p->id,
                'codigo' => $p->codigo,
                'nombre' => $p->nombre,
                'descripcion' => $p->descripcion,
                'unidad_medida' => $p->unidad_medida,
                'precio_venta' => (float) $p->precio_venta,
                'aplica_igv' => (bool) $p->aplica_igv,
                'serializado' => (bool) $p->serializado,
                'stock_minimo' => $p->stock_minimo !== null ? (int) $p->stock_minimo : null,
                'activo' => (bool) $p->activo,
                'stock_disponible' => $p->stockDisponible(),
            ];
        });

        // Contadores resumen para KPI rápidos
        $totalProductos = Product::count();
        $totalActivos = Product::where('activo', true)->count();
        $totalBajoMinimo = Product::query()->bajoMinimo()->count();

        return Inertia::render('gerente/productos/index', [
            'productos' => $productos,
            'filters' => [
                'buscar' => $buscar,
                'estado' => $estado,
                'bajo_minimo' => $bajoMinimo,
            ],
            'kpis' => [
                'totalProductos' => $totalProductos,
                'totalActivos' => $totalActivos,
                'totalBajoMinimo' => $totalBajoMinimo,
            ],
        ]);
    }

    public function store(StoreProductRequest $request, Team $current_team): RedirectResponse
    {
        Product::create($request->validated());

        return redirect()->route('gerente.productos.index', ['current_team' => $current_team])
            ->with('success', 'Producto creado exitosamente.');
    }

    public function update(UpdateProductRequest $request, Team $current_team, Product $producto): RedirectResponse
    {
        $producto->update($request->validated());

        return redirect()->route('gerente.productos.index', ['current_team' => $current_team])
            ->with('success', 'Producto actualizado exitosamente.');
    }

    public function destroy(Team $current_team, Product $producto): RedirectResponse
    {
        $producto->update(['activo' => false]);

        return redirect()->route('gerente.productos.index', ['current_team' => $current_team])
            ->with('success', 'Producto desactivado. Su historial se conserva.');
    }

    public function toggleStatus(Team $current_team, Product $producto): RedirectResponse
    {
        $producto->update(['activo' => ! $producto->activo]);

        $status = $producto->activo ? 'activado' : 'desactivado';

        return redirect()->back()->with('success', "Producto {$status} exitosamente.");
    }
}
