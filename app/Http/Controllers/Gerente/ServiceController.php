<?php

namespace App\Http\Controllers\Gerente;

use App\Http\Controllers\Controller;
use App\Http\Requests\Gerente\StoreServiceRequest;
use App\Http\Requests\Gerente\UpdateServiceRequest;
use App\Models\Service;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ServiceController extends Controller
{
    /**
     * Listado y gestión de servicios del catálogo (§86.4.2).
     */
    public function index(Team $current_team, Request $request): Response
    {
        $buscar = trim((string) $request->input('buscar', ''));
        $estado = (string) $request->input('estado', 'todos');

        $query = Service::query()
            ->when($buscar !== '', function ($q) use ($buscar) {
                $q->where(function ($sub) use ($buscar) {
                    $sub->where('nombre', 'like', "%{$buscar}%")
                        ->orWhere('codigo', 'like', "%{$buscar}%");
                });
            })
            ->when($estado === 'activos', fn ($q) => $q->where('activo', true))
            ->when($estado === 'inactivos', fn ($q) => $q->where('activo', false))
            ->orderBy('nombre');

        $servicios = $query->paginate(15)->withQueryString()->through(function (Service $s) {
            return [
                'id' => $s->id,
                'codigo' => $s->codigo,
                'nombre' => $s->nombre,
                'descripcion' => $s->descripcion,
                'unidad_medida' => $s->unidad_medida,
                'precio_venta' => (float) $s->precio_venta,
                'aplica_igv' => (bool) $s->aplica_igv,
                'activo' => (bool) $s->activo,
            ];
        });

        $totalServicios = Service::count();
        $totalActivos = Service::where('activo', true)->count();

        return Inertia::render('gerente/servicios/index', [
            'servicios' => $servicios,
            'filters' => [
                'buscar' => $buscar,
                'estado' => $estado,
            ],
            'kpis' => [
                'totalServicios' => $totalServicios,
                'totalActivos' => $totalActivos,
            ],
        ]);
    }

    public function store(StoreServiceRequest $request, Team $current_team): RedirectResponse
    {
        Service::create($request->validated());

        return redirect()->route('gerente.servicios.index', ['current_team' => $current_team])
            ->with('success', 'Servicio creado exitosamente.');
    }

    public function update(UpdateServiceRequest $request, Team $current_team, Service $servicio): RedirectResponse
    {
        $servicio->update($request->validated());

        return redirect()->route('gerente.servicios.index', ['current_team' => $current_team])
            ->with('success', 'Servicio actualizado exitosamente.');
    }

    public function destroy(Team $current_team, Service $servicio): RedirectResponse
    {
        // Regla no negociable (§86.4.2 y §60): nunca eliminar si tiene historial
        $hasHistory = $servicio->quoteItems()->exists() || $servicio->saleItems()->exists();

        if ($hasHistory) {
            return redirect()->back()
                ->with('error', 'No se puede eliminar el servicio porque cuenta con cotizaciones o ventas registradas. En su lugar, desactívelo.');
        }

        $servicio->delete();

        return redirect()->route('gerente.servicios.index', ['current_team' => $current_team])
            ->with('success', 'Servicio eliminado exitosamente.');
    }

    public function toggleStatus(Team $current_team, Service $servicio): RedirectResponse
    {
        $servicio->update(['activo' => ! $servicio->activo]);

        $status = $servicio->activo ? 'activado' : 'desactivado';

        return redirect()->back()->with('success', "Servicio {$status} exitosamente.");
    }
}
