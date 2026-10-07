<?php

namespace App\Http\Controllers\TecnicoCampo;

use App\Actions\TecnicoCampo\RegisterInstallation;
use App\Enums\EquipmentType;
use App\Http\Controllers\Controller;
use App\Models\CertificateType;
use App\Models\Equipment;
use App\Models\ServiceOrder;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class InstallationController extends Controller
{
    /**
     * Listado de órdenes de instalación en campo (§25).
     */
    public function index(Request $request, Team $current_team): Response
    {
        $tab = $request->query('tab', 'todos');
        $search = $request->query('q');

        $query = ServiceOrder::query()
            ->accessibleToTechnician($request->user())
            ->with(['client', 'sede', 'equipments'])
            ->where(function ($q) {
                $q->where('departamento_tecnico', 'campo')
                    ->orWhereHas('service', fn ($service) => $service->where('nombre', 'like', '%instalac%'));
            })
            ->latest('id');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('codigo', 'like', "%{$search}%")
                    ->orWhereHas('client', function ($sq) use ($search) {
                        $sq->where('razon_social', 'like', "%{$search}%")
                            ->orWhere('numero_documento', 'like', "%{$search}%");
                    });
            });
        }

        if ($tab === 'pendientes') {
            $query->whereIn('estado', ['pendiente_recepcion', 'recibido_planta']);
        } elseif ($tab === 'en_proceso') {
            $query->whereIn('estado', ['en_revision', 'en_proceso', 'esperando_autorizacion']);
        } elseif ($tab === 'finalizadas') {
            $query->whereIn('estado', ['listo_entrega', 'entregado', 'cerrado']);
        }

        $instalaciones = $query->paginate(15)->withQueryString();

        $stats = [
            'total' => ServiceOrder::query()
                ->where(fn ($q) => $q->where('departamento_tecnico', 'campo')->orWhereHas('service', fn ($service) => $service->where('nombre', 'like', '%instalac%')))
                ->count(),
            'pendientes' => ServiceOrder::query()
                ->where(fn ($q) => $q->where('departamento_tecnico', 'campo')->orWhereHas('service', fn ($service) => $service->where('nombre', 'like', '%instalac%')))
                ->whereIn('estado', ['pendiente_recepcion', 'recibido_planta'])
                ->count(),
            'en_proceso' => ServiceOrder::query()
                ->where(fn ($q) => $q->where('departamento_tecnico', 'campo')->orWhereHas('service', fn ($service) => $service->where('nombre', 'like', '%instalac%')))
                ->whereIn('estado', ['en_revision', 'en_proceso', 'esperando_autorizacion'])
                ->count(),
            'finalizadas' => ServiceOrder::query()
                ->where(fn ($q) => $q->where('departamento_tecnico', 'campo')->orWhereHas('service', fn ($service) => $service->where('nombre', 'like', '%instalac%')))
                ->whereIn('estado', ['listo_entrega', 'entregado', 'cerrado'])
                ->count(),
        ];

        return Inertia::render('tecnico-campo/instalaciones/index', [
            'instalaciones' => $instalaciones,
            'currentTab' => $tab,
            'search' => $search,
            'stats' => $stats,
        ]);
    }

    /**
     * Detalle y formulario táctil para registrar instalación en sitio (§25).
     */
    public function show(Team $current_team, ServiceOrder $serviceOrder): Response
    {
        $serviceOrder->load([
            'client',
            'sede',
            'equipments',
            'events' => fn ($q) => $q->latest(),
            'certificates.certificateType',
        ]);

        $customerEquipments = Equipment::query()
            ->where('client_id', $serviceOrder->client_id)
            ->get();

        $certificateTypes = CertificateType::query()
            ->orderBy('nombre')
            ->get(['id', 'codigo', 'nombre']);

        return Inertia::render('tecnico-campo/instalaciones/show', [
            'asignacion' => $serviceOrder->asignacionPara(request()->user()),
            'order' => $serviceOrder,
            'customerEquipments' => $customerEquipments,
            'certificateTypes' => $certificateTypes,
        ]);
    }

    /**
     * Procesa el registro de la instalación técnica en campo (§25).
     */
    public function store(
        Request $request,
        Team $current_team,
        ServiceOrder $serviceOrder,
        RegisterInstallation $registerInstallation
    ): RedirectResponse {
        $validated = $request->validate([
            'area' => ['required', 'string', 'max:150'],
            'ubicacion_instalada' => ['required', 'string', 'max:200'],
            'pruebas' => ['nullable', 'string', 'max:500'],
            'foto_antes_path' => ['nullable', 'string', 'max:255'],
            'foto_despues_path' => ['nullable', 'string', 'max:255'],
            'observaciones' => ['nullable', 'string', 'max:1000'],
            'conformidad_nombre' => ['required', 'string', 'max:150'],
            'conformidad_aceptada' => ['required', 'accepted'],
            'emitir_certificado' => ['nullable', 'boolean'],
            'tipo_certificado_codigo' => ['nullable', 'string', 'max:50'],
            'equipos' => ['required', 'array', 'min:1'],
            'equipos.*.equipment_id' => ['nullable', 'integer', 'exists:equipment,id'],
            'equipos.*.numero_serie' => ['nullable', 'string', 'max:50'],
            'equipos.*.tipo_agente' => ['nullable', Rule::in(EquipmentType::etiquetas())],
            'equipos.*.capacidad' => ['nullable', 'string', 'max:30'],
            'equipos.*.marca' => ['nullable', 'string', 'max:100'],
            'equipos.*.ubicacion_actual' => ['nullable', 'string', 'max:150'],
            'equipos.*.serie_fabricante' => ['nullable', 'string', 'max:50'],
            'equipos.*.anio_fabricacion' => ['nullable', 'integer', 'min:1970', 'max:'.(date('Y') + 1)],
        ]);

        $registerInstallation->execute($serviceOrder, $request->user(), $validated);

        return redirect()
            ->route('tecnico-campo.instalaciones.show', [
                'current_team' => $current_team,
                'service_order' => $serviceOrder->id,
            ])
            ->with('success', 'Instalación registrada con éxito. Equipos vinculados al historial del cliente.');
    }
}
