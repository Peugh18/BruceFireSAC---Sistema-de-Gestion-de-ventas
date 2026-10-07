<?php

namespace App\Http\Controllers\Gerente;

use App\Enums\EquipmentType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Gerente\StoreProductRequest;
use App\Http\Requests\Gerente\UpdateProductRequest;
use App\Models\Product;
use App\Models\Team;
use App\Services\AuditLogger;
use App\Services\Billing\AfectacionIgv;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
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
                        ->orWhere('codigo', 'like', "%{$buscar}%")
                        ->orWhere('codigo_barras', $buscar);
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
                'codigo_barras' => $p->codigo_barras,
                'categoria' => $p->categoria,
                'agente' => $p->agente,
                'capacidad' => $p->capacidad,
                'nombre' => $p->nombre,
                'descripcion' => $p->descripcion,
                'unidad_medida' => $p->unidad_medida,
                'precio_venta' => (float) $p->precio_venta,
                'aplica_igv' => (bool) $p->aplica_igv,
                'tipo_afectacion_igv' => $p->tipo_afectacion_igv,
                'igv_requiere_revision' => AfectacionIgv::requiereRevision($p),
                'serializado' => (bool) $p->serializado,
                'controla_lote' => (bool) $p->controla_lote,
                'unidad_compra' => $p->unidad_compra,
                'factor_compra' => (int) $p->factor_compra,
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
            'categorias' => Product::CATEGORIAS,
            'agentes' => EquipmentType::opciones(),
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
        $datos = $request->validated();
        if (isset($datos['tipo_afectacion_igv'])) {
            $datos['igv_revisado_at'] = now();
        } else {
            $datos['tipo_afectacion_igv'] = '10';
        }
        Product::create($datos);

        return redirect()->route('gerente.productos.index', ['current_team' => $current_team])
            ->with('success', 'Producto creado exitosamente.');
    }

    public function update(UpdateProductRequest $request, Team $current_team, Product $producto): RedirectResponse
    {
        $datos = $request->validated();
        if (isset($datos['tipo_afectacion_igv'])) {
            $datos['igv_revisado_at'] = now();
        }

        // Con stock o ventas registradas, cambiar "con serie" / "sin serie"
        // descuadraría el Kardex: se crea otro producto.
        if (array_key_exists('serializado', $datos)
            && (bool) $datos['serializado'] !== (bool) $producto->serializado
            && ($producto->movements()->exists() || $producto->units()->exists() || $producto->saleItems()->exists())) {
            throw ValidationException::withMessages([
                'serializado' => 'Este producto ya tiene movimientos o ventas: no se puede cambiar si lleva número de serie. Crea un producto nuevo.',
            ]);
        }

        $antes = $producto->only(['nombre', 'precio_venta', 'serializado', 'activo', 'codigo_barras']);
        $producto->update($datos);

        AuditLogger::log(
            action: 'producto.actualizado',
            entity: $producto,
            oldValues: $antes,
            newValues: $producto->only(array_keys($antes)),
            userId: $request->user()?->id,
        );

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
