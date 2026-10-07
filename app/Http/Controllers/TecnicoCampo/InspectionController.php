<?php

namespace App\Http\Controllers\TecnicoCampo;

use App\Actions\Certificates\IssueCertificate;
use App\Actions\Tecnico\ProcessChecklist;
use App\Actions\TecnicoCampo\EquipoDeLaOrden;
use App\Enums\EquipmentType;
use App\Http\Controllers\Controller;
use App\Models\CertificateType;
use App\Models\Equipment;
use App\Models\ServiceOrder;
use App\Models\ServiceOrderEvent;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class InspectionController extends Controller
{
    /**
     * Listado de inspecciones de campo (§24).
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
                    ->orWhereHas('service', fn ($service) => $service->where('nombre', 'like', '%inspecci%'));
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

        $inspecciones = $query->paginate(15)->withQueryString();

        $baseStatsQuery = ServiceOrder::query()
            ->accessibleToTechnician($request->user())
            ->where(function ($q) {
                $q->where('departamento_tecnico', 'campo')
                    ->orWhereHas('service', fn ($service) => $service->where('nombre', 'like', '%inspecci%'));
            });

        $stats = [
            'total' => (clone $baseStatsQuery)->count(),
            'pendientes' => (clone $baseStatsQuery)->whereIn('estado', ['pendiente_recepcion', 'recibido_planta'])->count(),
            'en_proceso' => (clone $baseStatsQuery)->whereIn('estado', ['en_revision', 'en_proceso', 'esperando_autorizacion'])->count(),
            'finalizadas' => (clone $baseStatsQuery)->whereIn('estado', ['listo_entrega', 'entregado', 'cerrado'])->count(),
        ];

        return Inertia::render('tecnico-campo/inspecciones/index', [
            'inspecciones' => $inspecciones,
            'currentTab' => $tab,
            'search' => $search,
            'stats' => $stats,
        ]);
    }

    /**
     * Detalle de inspección en sitio con tarjetas de extintores y checklists (§24).
     */
    public function show(Team $current_team, ServiceOrder $serviceOrder): Response
    {
        $serviceOrder->load([
            'client',
            'sede',
            'equipments.checklists' => fn ($q) => $q->where('service_order_id', $serviceOrder->id)->latest(),
            'deficiencies.equipment',
            'events' => fn ($q) => $q->latest(),
            'certificates.certificateType',
        ]);

        // Equipos registrados previamente del cliente para agregarlos con 1 tap si faltaban
        $customerEquipments = Equipment::query()
            ->where('client_id', $serviceOrder->client_id)
            ->whereNotIn('id', $serviceOrder->equipments->pluck('id'))
            ->get();

        return Inertia::render('tecnico-campo/inspecciones/show', [
            'asignacion' => $serviceOrder->asignacionPara(request()->user()),
            'order' => $serviceOrder,
            'customerEquipments' => $customerEquipments,
            'elementosChecklist' => ProcessChecklist::ELEMENTOS,
        ]);
    }

    /**
     * Registra o vincula un extintor a la inspección en campo (§24).
     */
    public function storeEquipment(Request $request, Team $current_team, ServiceOrder $serviceOrder, EquipoDeLaOrden $equipoDeLaOrden): RedirectResponse
    {
        $validated = $request->validate([
            'equipment_id' => ['nullable', 'exists:equipment,id'],
            'numero_serie' => ['nullable', 'string', 'max:50'],
            'tipo_agente' => ['nullable', Rule::in(EquipmentType::etiquetas())],
            'capacidad' => ['nullable', 'string', 'max:30'],
            'marca' => ['nullable', 'string', 'max:100'],
            'ubicacion_actual' => ['nullable', 'string', 'max:150'],
            'anio_fabricacion' => ['nullable', 'integer', 'min:1970', 'max:'.(date('Y') + 1)],
            'serie_fabricante' => ['nullable', 'string', 'max:50'],
        ]);

        EquipoDeLaOrden::asegurarAbierta($serviceOrder);

        // Uno ya registrado del mismo cliente (también por su serie) o uno
        // nuevo sin fechas inventadas: solo se inspecciona.
        $equipment = $equipoDeLaOrden->resolver($serviceOrder, $validated, instalado: false);

        if (! $serviceOrder->equipments()->where('equipment_id', $equipment->id)->exists()) {
            $serviceOrder->equipments()->attach($equipment->id, [
                'recibido' => true,
                'observaciones' => 'Inspección en campo',
            ]);
        }

        ServiceOrderEvent::create([
            'service_order_id' => $serviceOrder->id,
            'tipo' => 'otro',
            'user_id' => $request->user()->id,
            'payload' => [
                'accion' => 'equipo_incluido_inspeccion',
                'equipment_id' => $equipment->id,
                'numero_serie' => $equipment->numero_serie,
                'ubicacion' => $equipment->ubicacion_actual,
            ],
        ]);

        return back()->with('success', "Extintor {$equipment->numero_serie} agregado a la inspección.");
    }

    /**
     * Procesa el checklist técnico de un extintor en campo (§19, §24).
     */
    public function storeChecklist(
        Request $request,
        Team $current_team,
        ServiceOrder $serviceOrder,
        Equipment $equipment,
        ProcessChecklist $processChecklist
    ): RedirectResponse {
        $validated = $request->validate([
            'items' => ['required', 'array'],
            'observaciones' => ['nullable', 'string', 'max:1000'],
        ]);

        EquipoDeLaOrden::asegurarAbierta($serviceOrder);

        $processChecklist->execute($serviceOrder, $equipment, $request->user(), [
            'origen' => 'campo',
            'items' => $validated['items'],
            'observaciones' => $validated['observaciones'] ?? null,
        ]);

        return back()->with('success', "Checklist completado para extintor {$equipment->numero_serie}.");
    }

    /**
     * Cierra la inspección en campo con acta/firma de conformidad (§24).
     */
    public function complete(Request $request, Team $current_team, ServiceOrder $serviceOrder): RedirectResponse
    {
        $validated = $request->validate([
            'responsable' => ['nullable', 'string', 'max:150'],
            'cargo' => ['nullable', 'string', 'max:100'],
            'conformidad_nombre' => ['required', 'string', 'max:150'],
            'conformidad_aceptada' => ['required', 'accepted'],
            'observaciones_generales' => ['nullable', 'string', 'max:1000'],
        ]);

        // Finalizar dos veces duplicaría el certificado.
        EquipoDeLaOrden::asegurarAbierta($serviceOrder);

        $responsable = ($validated['responsable'] ?? null) ?: $request->user()->name;
        $cargo = ($validated['cargo'] ?? null) ?: 'Técnico de Campo';

        // Evento de inspección completada con eslabón de custodia (§22.4, §24, §85.6.3)
        ServiceOrderEvent::create([
            'service_order_id' => $serviceOrder->id,
            'tipo' => 'trabajo_completado',
            'user_id' => $request->user()->id,
            'payload' => [
                'accion' => 'inspeccion_campo_finalizada',
                'responsable' => $responsable,
                'cargo' => $cargo,
                'conformidad_nombre' => $validated['conformidad_nombre'],
                'observaciones_generales' => $validated['observaciones_generales'] ?? null,
                'fecha_finalizado' => now()->toIso8601String(),
                'eslabon_custodia' => 'inspeccion_campo',
                'equipos_inspeccionados_count' => $serviceOrder->equipments()->count(),
            ],
        ]);

        // Si hay deficiencias que requieren autorización comercial, pasar a esperando_autorizacion
        $deficienciasPendientes = $serviceOrder->deficiencies()
            ->where('requiere_autorizacion', true)
            ->where('estado', 'esperando_autorizacion')
            ->count();

        if ($deficienciasPendientes > 0) {
            $serviceOrder->update(['estado' => 'esperando_autorizacion']);
            $msg = 'Inspección finalizada. Hay deficiencias reportadas que requieren autorización de Vendedor.';
        } else {
            $serviceOrder->update(['estado' => 'listo_entrega']);

            // El certificado solo lleva los extintores cuyo último checklist
            // de esta orden salió conforme: un observado, descargado o sin
            // revisar no se certifica como operativo.
            $conformes = $serviceOrder->equipments()
                ->where('equipment.estado', '!=', 'descargado')
                ->get()
                ->filter(fn (Equipment $eq) => $serviceOrder->checklists()->where('equipment_id', $eq->id)->latest('id')->value('resultado_general') === 'conforme')
                ->values();

            $certType = CertificateType::where('codigo', 'operatividad_garantia')->first();
            if ($certType && $conformes->isNotEmpty()) {
                $serviceOrder->loadMissing('client');

                app(IssueCertificate::class)->handle(
                    $certType,
                    $serviceOrder->client,
                    $conformes->map(fn (Equipment $eq) => [
                        'equipment_id' => $eq->id,
                        'numero_serie' => $eq->numero_serie,
                    ])->all(),
                    $serviceOrder->sale_id,
                    $serviceOrder->id,
                    ['tipo_atencion' => 'inspeccion'],
                );
            }

            $msg = $conformes->isNotEmpty()
                ? "Inspección finalizada y certificado de operatividad emitido para {$conformes->count()} extintor(es) conforme(s)."
                : 'Inspección finalizada. Ningún extintor salió conforme: no se emitió certificado.';
        }

        return redirect()
            ->route('tecnico-campo.inspecciones.show', [
                'current_team' => $current_team,
                'service_order' => $serviceOrder->id,
            ])
            ->with('success', $msg);
    }
}
