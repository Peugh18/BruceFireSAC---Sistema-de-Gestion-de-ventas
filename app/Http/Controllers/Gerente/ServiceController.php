<?php

namespace App\Http\Controllers\Gerente;

use App\Enums\EquipmentType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Gerente\StoreServiceRequest;
use App\Http\Requests\Gerente\UpdateServiceRequest;
use App\Models\CertificateType;
use App\Models\ProductCategory;
use App\Models\Service;
use App\Models\Team;
use App\Models\TipoDeServicio;
use App\Services\Billing\AfectacionIgv;
use App\Support\CatalogosSunat;
use App\Support\UnidadMedidaSunat;
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

        $query = Service::query()->with('tipoDeServicio:id,nombre')
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
                'categoria' => $s->categoria,
                'unidad_medida' => $s->unidad_medida,
                'agente' => EquipmentType::nombreDe($s->agente),
                'capacidad' => $s->capacidad,
                'precio_venta' => (float) $s->precio_venta,
                'codigo_detraccion' => $s->codigo_detraccion,
                'estado_detraccion' => $s->estado_detraccion ?? 'pendiente',
                'aplica_igv' => (bool) $s->aplica_igv,
                'tipo_afectacion_igv' => $s->tipo_afectacion_igv,
                'igv_requiere_revision' => AfectacionIgv::requiereRevision($s),
                'certificate_type_id' => $s->certificate_type_id,
                'tipo_de_servicio_id' => $s->tipo_de_servicio_id,
                'tipo' => $s->tipoDeServicio?->nombre,
                'activo' => (bool) $s->activo,
            ];
        });

        $totalServicios = Service::count();
        $totalActivos = Service::where('activo', true)->count();

        return Inertia::render('gerente/servicios/index', [
            'servicios' => $servicios,
            'tiposDeServicio' => TipoDeServicio::query()->with('certificateType:id,nombre')->orderBy('id')->get()->map(fn (TipoDeServicio $tipo) => [
                'id' => $tipo->id,
                'nombre' => $tipo->nombre,
                'codigo_detraccion' => $tipo->codigo_detraccion,
                'estado_detraccion' => $tipo->estado_detraccion ?? 'pendiente',
                'detraccion' => $tipo->codigo_detraccion ? CatalogosSunat::detraccionConNombre($tipo->codigo_detraccion) : null,
                'certificate_type_id' => $tipo->certificate_type_id,
                'certificado' => $tipo->certificateType?->nombre,
                'codigo_sunat' => $tipo->codigo_sunat,
                'pide_extintor' => $tipo->pide_extintor,
                'atencion' => $tipo->atencion,
                'activo' => $tipo->activo,
            ]),
            'detracciones' => CatalogosSunat::detracciones(),
            'categorias' => ProductCategory::conUsos(),
            'unidadesMedida' => UnidadMedidaSunat::opciones(),
            'tiposCertificado' => CertificateType::query()->orderBy('nombre')->get(['id', 'nombre']),
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
        $datos = $request->validated();
        if (isset($datos['tipo_afectacion_igv'])) {
            $datos['igv_revisado_at'] = now();
        } else {
            $datos['tipo_afectacion_igv'] = '10';
        }
        Service::create($datos);

        return redirect()->route('gerente.servicios.index', ['current_team' => $current_team])
            ->with('success', 'Servicio creado exitosamente.');
    }

    public function update(UpdateServiceRequest $request, Team $current_team, Service $servicio): RedirectResponse
    {
        $datos = $request->validated();
        if (isset($datos['tipo_afectacion_igv'])) {
            $datos['igv_revisado_at'] = now();
        }
        $servicio->update($datos);

        return redirect()->route('gerente.servicios.index', ['current_team' => $current_team])
            ->with('success', 'Servicio actualizado exitosamente.');
    }

    public function destroy(Team $current_team, Service $servicio): RedirectResponse
    {
        $servicio->update(['activo' => false]);

        return redirect()->route('gerente.servicios.index', ['current_team' => $current_team])
            ->with('success', 'Servicio desactivado. Su historial se conserva.');
    }

    public function toggleStatus(Team $current_team, Service $servicio): RedirectResponse
    {
        $servicio->update(['activo' => ! $servicio->activo]);

        $status = $servicio->activo ? 'activado' : 'desactivado';

        return redirect()->back()->with('success', "Servicio {$status} exitosamente.");
    }
}
