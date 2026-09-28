<?php

namespace App\Http\Controllers\TecnicoCampo;

use App\Actions\Certificates\IssueCertificate;
use App\Actions\Tecnico\ProcessChecklist;
use App\Http\Controllers\Controller;
use App\Models\CertificateType;
use App\Models\Equipment;
use App\Models\ServiceOrder;
use App\Models\ServiceOrderEvent;
use App\Models\Team;
use App\Services\Inventory\InventorySequenceGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

        $stats = [
            'total' => ServiceOrder::query()
                ->where(fn ($q) => $q->where('departamento_tecnico', 'campo')->orWhereHas('service', fn ($service) => $service->where('nombre', 'like', '%inspecci%')))
                ->count(),
            'pendientes' => ServiceOrder::query()
                ->where(fn ($q) => $q->where('departamento_tecnico', 'campo')->orWhereHas('service', fn ($service) => $service->where('nombre', 'like', '%inspecci%')))
                ->whereIn('estado', ['pendiente_recepcion', 'recibido_planta'])
                ->count(),
            'en_proceso' => ServiceOrder::query()
                ->where(fn ($q) => $q->where('departamento_tecnico', 'campo')->orWhereHas('service', fn ($service) => $service->where('nombre', 'like', '%inspecci%')))
                ->whereIn('estado', ['en_revision', 'en_proceso', 'esperando_autorizacion'])
                ->count(),
            'finalizadas' => ServiceOrder::query()
                ->where(fn ($q) => $q->where('departamento_tecnico', 'campo')->orWhereHas('service', fn ($service) => $service->where('nombre', 'like', '%inspecci%')))
                ->whereIn('estado', ['listo_entrega', 'entregado', 'cerrado'])
                ->count(),
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
    public function storeEquipment(Request $request, Team $current_team, ServiceOrder $serviceOrder): RedirectResponse
    {
        $validated = $request->validate([
            'equipment_id' => ['nullable', 'exists:equipment,id'],
            'numero_serie' => ['nullable', 'string', 'max:50'],
            'tipo_agente' => ['nullable', 'string', 'max:50'],
            'capacidad' => ['nullable', 'string', 'max:30'],
            'marca' => ['nullable', 'string', 'max:100'],
            'ubicacion_actual' => ['nullable', 'string', 'max:150'],
            'anio_fabricacion' => ['nullable', 'integer', 'min:1970', 'max:'.(date('Y') + 1)],
            'serie_fabricante' => ['nullable', 'string', 'max:50'],
        ]);

        if (! empty($validated['equipment_id'])) {
            $equipment = Equipment::findOrFail($validated['equipment_id']);
        } else {
            $serial = ! empty($validated['numero_serie'])
                ? $validated['numero_serie']
                : app(InventorySequenceGenerator::class)->nextEquipmentSerial();

            $equipment = Equipment::create([
                'client_id' => $serviceOrder->client_id,
                'numero_serie' => $serial,
                'tipo_agente' => $validated['tipo_agente'] ?? 'PQS',
                'capacidad' => $validated['capacidad'] ?? '6 kg',
                'marca' => $validated['marca'] ?? 'Genérica / Sin marca',
                'serie_fabricante' => $validated['serie_fabricante'] ?? null,
                'anio_fabricacion' => $validated['anio_fabricacion'] ?? null,
                'ubicacion_actual' => $validated['ubicacion_actual'] ?? 'Sede cliente',
                'estado' => 'operativo',
                'fecha_venta' => now(),
            ]);
        }

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

        $responsable = $validated['responsable'] ?: $request->user()->name;
        $cargo = $validated['cargo'] ?: 'Técnico de Campo';

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

            // Si el cliente no tiene observaciones pendientes, auto emitir certificado si aplica
            $certType = CertificateType::where('codigo', 'operatividad_garantia')->first();
            if ($certType) {
                $serviceOrder->loadMissing(['client', 'equipments']);
                $unidades = $serviceOrder->equipments->map(function (Equipment $eq) {
                    return [
                        'equipment_id' => $eq->id,
                        'numero_serie' => $eq->numero_serie,
                        'fecha_ultima_recarga' => now()->toDateString(),
                    ];
                })->all();

                app(IssueCertificate::class)->handle(
                    $certType,
                    $serviceOrder->client,
                    $unidades,
                    $serviceOrder->sale_id,
                    $serviceOrder->id
                );
            }

            $msg = 'Inspección finalizada con éxito y certificado de operatividad emitido.';
        }

        return redirect()
            ->route('tecnico-campo.inspecciones.show', [
                'current_team' => $current_team,
                'service_order' => $serviceOrder->id,
            ])
            ->with('success', $msg);
    }
}
