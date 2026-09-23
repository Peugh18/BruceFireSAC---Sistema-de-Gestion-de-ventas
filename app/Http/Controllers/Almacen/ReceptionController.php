<?php

namespace App\Http\Controllers\Almacen;

use App\Actions\Almacen\CreateReception;
use App\Actions\Almacen\UpdateReception;
use App\Http\Controllers\Controller;
use App\Http\Requests\Almacen\StoreReceptionRequest;
use App\Http\Requests\Almacen\UpdateReceptionRequest;
use App\Models\Product;
use App\Models\Reception;
use App\Models\Sede;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReceptionController extends Controller
{
    /**
     * Listado de recepciones de proveedor (§84.8).
     */
    public function index(Team $current_team, Request $request): Response
    {
        $search = $request->string('search')->toString();
        $almacenId = $request->user()->almacenRestringidoId();
        $sedeId = $almacenId ?? $request->integer('sede_id');
        $fechaDesde = $request->string('fecha_desde')->toString();
        $fechaHasta = $request->string('fecha_hasta')->toString();

        $sedes = Sede::query()
            ->whereIn('tipo', ['almacen', 'mixta'])
            ->where('activo', true)
            ->when($almacenId, fn ($q) => $q->where('id', $almacenId))
            ->get(['id', 'nombre', 'ciudad']);

        $receptions = Reception::query()
            ->with(['sedeAlmacen:id,nombre', 'user:id,name', 'items.product:id,codigo,nombre,serializado'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('proveedor', 'like', "%{$search}%")
                        ->orWhere('documento_referencia', 'like', "%{$search}%");
                });
            })
            ->when($sedeId > 0, fn ($q) => $q->where('sede_almacen_id', $sedeId))
            ->when($fechaDesde !== '', fn ($q) => $q->whereDate('fecha', '>=', $fechaDesde))
            ->when($fechaHasta !== '', fn ($q) => $q->whereDate('fecha', '<=', $fechaHasta))
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString()
            ->through(function (Reception $reception) {
                $totalRecibido = (int) $reception->items->sum('cantidad');
                $totalConforme = (int) $reception->items->sum('cantidad_conforme');
                $totalNoConforme = $totalRecibido - $totalConforme;

                return [
                    'id' => $reception->id,
                    'proveedor' => $reception->proveedor,
                    'documento_referencia' => $reception->documento_referencia,
                    'fecha' => $reception->fecha->toDateString(),
                    'sede' => $reception->sedeAlmacen->nombre,
                    'usuario' => $reception->user?->name ?? 'Sistema',
                    'total_recibido' => $totalRecibido,
                    'total_conforme' => $totalConforme,
                    'total_no_conforme' => $totalNoConforme,
                    'tiene_no_conforme' => $totalNoConforme > 0,
                    'items_count' => $reception->items->count(),
                ];
            });

        return Inertia::render('almacen/recepciones/index', [
            'receptions' => $receptions,
            'sedes' => $sedes,
            'filters' => [
                'search' => $search,
                'sede_id' => $sedeId ?: null,
                'fecha_desde' => $fechaDesde ?: null,
                'fecha_hasta' => $fechaHasta ?: null,
            ],
            'kpis' => [
                'total_recepciones' => Reception::count(),
                'recepciones_hoy' => Reception::whereDate('fecha', today())->count(),
                'unidades_recibidas_mes' => (int) \DB::table('reception_items')
                    ->join('receptions', 'reception_items.reception_id', '=', 'receptions.id')
                    ->whereBetween('receptions.fecha', [now()->startOfMonth(), now()->endOfMonth()])
                    ->sum('reception_items.cantidad_conforme'),
            ],
        ]);
    }

    /**
     * Formulario para nueva recepción (§84.8).
     */
    public function create(Team $current_team, Request $request): Response
    {
        $almacenId = $request->user()->almacenRestringidoId();

        $sedes = Sede::query()
            ->whereIn('tipo', ['almacen', 'mixta'])
            ->where('activo', true)
            ->when($almacenId, fn ($q) => $q->where('id', $almacenId))
            ->get(['id', 'nombre', 'tipo', 'ciudad']);

        $products = Product::query()
            ->where('activo', true)
            ->orderBy('nombre')
            ->get(['id', 'codigo', 'nombre', 'unidad_medida', 'serializado']);

        return Inertia::render('almacen/recepciones/create', [
            'sedes' => $sedes,
            'products' => $products,
            'today' => today()->toDateString(),
            'current_year' => (int) date('Y'),
        ]);
    }

    /**
     * Guarda la nueva recepción y genera el Kardex / unidades (§84.8).
     */
    public function store(
        Team $current_team,
        StoreReceptionRequest $request,
        CreateReception $createReception
    ): RedirectResponse {
        $reception = $createReception->handle(
            $request->validated(),
            $request->validated('items'),
            $request->user()
        );

        return redirect()->route('almacen.recepciones.show', [
            'current_team' => $current_team,
            'reception' => $reception,
        ]);
    }

    /**
     * Ver y editar una recepción confirmada (§84.8).
     */
    public function show(Team $current_team, Reception $reception): Response
    {
        $reception->load([
            'sedeAlmacen:id,nombre,tipo,ciudad',
            'user:id,name',
            'items.product:id,codigo,nombre,unidad_medida,serializado',
            'movements.inventoryUnit:id,numero_serie,marca,anio_fabricacion,estado',
        ]);

        return Inertia::render('almacen/recepciones/show', [
            'reception' => [
                'id' => $reception->id,
                'proveedor' => $reception->proveedor,
                'documento_referencia' => $reception->documento_referencia,
                'fecha' => $reception->fecha->toDateString(),
                'sede' => $reception->sedeAlmacen,
                'usuario' => $reception->user?->name,
                'observacion' => $reception->observacion,
                'items' => $reception->items->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'product_id' => $item->product_id,
                        'producto' => [
                            'id' => $item->product->id,
                            'codigo' => $item->product->codigo,
                            'nombre' => $item->product->nombre,
                            'unidad_medida' => $item->product->unidad_medida,
                            'serializado' => $item->product->serializado,
                        ],
                        'cantidad' => $item->cantidad,
                        'cantidad_conforme' => $item->cantidad_conforme,
                        'observacion_item' => $item->observacion_item,
                    ];
                }),
                'unidades_serializadas' => $reception->movements
                    ->filter(fn ($m) => $m->inventoryUnit !== null)
                    ->map(fn ($m) => [
                        'id' => $m->inventoryUnit->id,
                        'numero_serie' => $m->inventoryUnit->numero_serie,
                        'marca' => $m->inventoryUnit->marca,
                        'anio_fabricacion' => $m->inventoryUnit->anio_fabricacion,
                        'estado' => $m->inventoryUnit->estado,
                        'product_id' => $m->product_id,
                    ])
                    ->values()
                    ->all(),
            ],
            'current_year' => (int) date('Y'),
        ]);
    }

    /**
     * Actualiza la recepción confirmada registrando compensaciones (§84.8).
     */
    public function update(
        Team $current_team,
        Reception $reception,
        UpdateReceptionRequest $request,
        UpdateReception $updateReception
    ): RedirectResponse {
        $updateReception->handle(
            $reception,
            $request->validated(),
            $request->validated('items'),
            $request->user()
        );

        return redirect()->route('almacen.recepciones.show', [
            'current_team' => $current_team,
            'reception' => $reception,
        ]);
    }
}
