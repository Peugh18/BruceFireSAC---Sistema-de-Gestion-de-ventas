<?php

namespace App\Http\Controllers\TecnicoCampo;

use App\Actions\Certificates\IssueCertificate;
use App\Actions\Tecnico\GuardarEvidencia;
use App\Actions\Tecnico\ProcessChecklist;
use App\Actions\TecnicoCampo\EquipoDeLaOrden;
use App\Enums\EquipmentType;
use App\Http\Controllers\Controller;
use App\Models\CertificateType;
use App\Models\Equipment;
use App\Models\ServiceOrder;
use App\Models\ServiceOrderEvent;
use App\Models\Team;
use App\Services\Reports\ActaConformidadPdfService;
use App\Services\Tecnico\ConversacionDeLaOrden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Mantenimiento en sitio (T2): el técnico de campo revisa cada extintor en
 * el local del cliente con el mismo checklist de planta, deja fotos de antes
 * y después, el cliente firma y se imprime el acta.
 */
class MaintenanceController extends Controller
{
    public function index(Request $request, Team $current_team): Response
    {
        $buscar = $request->query('q');

        $mantenimientos = $this->ordenes($request)
            ->with(['client', 'sede', 'equipments'])
            ->when($buscar, fn ($query) => $query->where(fn ($q) => $q
                ->where('codigo', 'like', "%{$buscar}%")
                ->orWhereHas('client', fn ($c) => $c->where('razon_social', 'like', "%{$buscar}%")->orWhere('numero_documento', 'like', "%{$buscar}%"))))
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('tecnico-campo/mantenimientos/index', [
            'mantenimientos' => $mantenimientos,
            'search' => $buscar,
        ]);
    }

    public function show(Team $current_team, ServiceOrder $serviceOrder): Response
    {
        $serviceOrder->load([
            'client',
            'sede',
            'equipments.checklists' => fn ($q) => $q->where('service_order_id', $serviceOrder->id)->latest(),
            'deficiencies.equipment',
            'certificates.certificateType',
        ]);

        return Inertia::render('tecnico-campo/mantenimientos/show', [
            'asignacion' => $serviceOrder->asignacionPara(request()->user()),
            'order' => $serviceOrder,
            'customerEquipments' => Equipment::query()->where('client_id', $serviceOrder->client_id)->orderBy('numero_serie')->get(['id', 'numero_serie', 'tipo_agente', 'capacidad']),
            'elementosChecklist' => ProcessChecklist::ELEMENTOS,
            'evidencias' => $serviceOrder->evidencias()->whereIn('etapa', ['antes', 'despues'])->get(['id', 'etapa', 'tipo', 'equipment_id']),
            'conversacion' => app(ConversacionDeLaOrden::class)->paraPagina($serviceOrder),
            'finalizado' => $this->finalizado($serviceOrder),
        ]);
    }

    /**
     * Agrega un extintor a la visita: uno ya registrado del cliente (por
     * escaneo del código) o uno nuevo.
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
        ]);

        EquipoDeLaOrden::asegurarAbierta($serviceOrder);

        $equipment = $equipoDeLaOrden->resolver($serviceOrder, $validated, instalado: false);

        if (! $serviceOrder->equipments()->where('equipment_id', $equipment->id)->exists()) {
            $serviceOrder->equipments()->attach($equipment->id, ['recibido' => true, 'observaciones' => 'Mantenimiento en sitio']);
        }

        return back()->with('success', "Extintor {$equipment->numero_serie} agregado al mantenimiento.");
    }

    public function storeChecklist(
        Request $request,
        Team $current_team,
        ServiceOrder $serviceOrder,
        Equipment $equipment,
        ProcessChecklist $processChecklist,
    ): RedirectResponse {
        $validated = $request->validate([
            'items' => ['required', 'array'],
            'items.*.foto' => ['nullable', 'image', 'max:15360'],
            'observaciones' => ['nullable', 'string', 'max:1000'],
        ]);

        EquipoDeLaOrden::asegurarAbierta($serviceOrder);

        // Laravel excluye 'items' del array que devuelve validate() porque el
        // campo tiene reglas anidadas (items.*.foto) y, con
        // excludeUnvalidatedArrayKeys activado, las claves padre con hijos sin
        // validar se descartan del resultado (mismo caso que
        // InspectionController::storeChecklist). La validación de 'items'
        // (required|array) y de las fotos sí se ejecutó, así que se toma del
        // request —que incluye los archivos ya validados— con un valor por
        // defecto para que un request malformado no rompa con un 500.
        $items = $validated['items'] ?? data_get($request->all(), 'items', []);

        $processChecklist->execute($serviceOrder, $equipment, $request->user(), [
            'origen' => 'campo',
            'items' => $items,
            'observaciones' => $validated['observaciones'] ?? null,
        ]);

        return back()->with('success', "Checklist guardado para {$equipment->numero_serie}.");
    }

    /**
     * Cierra el mantenimiento: exige al menos un checklist, fotos de antes y
     * después, y la firma del cliente.
     */
    public function complete(Request $request, Team $current_team, ServiceOrder $serviceOrder, GuardarEvidencia $guardarEvidencia): RedirectResponse
    {
        $validated = $request->validate([
            'conformidad_nombre' => ['required', 'string', 'max:150'],
            'conformidad_aceptada' => ['required', 'accepted'],
            'firma' => ['required', 'string', 'max:1500000', 'starts_with:data:image/png;base64,'],
            'observaciones_generales' => ['nullable', 'string', 'max:1000'],
        ], ['firma.required' => 'El cliente debe firmar en la pantalla.']);

        $mensaje = DB::transaction(function () use ($request, $validated, $serviceOrder, $guardarEvidencia): string {
            // Finalizar dos veces duplicaría el certificado: la orden se
            // bloquea y se revalida DENTRO de la transacción (A2), junto con
            // el evento, el estado y el certificado.
            $serviceOrder = ServiceOrder::query()->lockForUpdate()->findOrFail($serviceOrder->id);
            EquipoDeLaOrden::asegurarAbierta($serviceOrder);

            if (! $serviceOrder->checklists()->exists()) {
                throw ValidationException::withMessages(['checklist' => 'Completa el checklist de al menos un extintor.']);
            }

            foreach (['antes' => 'antes', 'despues' => 'después'] as $etapa => $texto) {
                if (! $serviceOrder->evidencias()->where('etapa', $etapa)->where('tipo', 'foto')->exists()) {
                    throw ValidationException::withMessages(['fotos' => "Falta al menos una foto de {$texto} del mantenimiento."]);
                }
            }

            $guardarEvidencia->firma($serviceOrder, $validated['firma'], 'mantenimiento', $request->user());

            ServiceOrderEvent::create([
                'service_order_id' => $serviceOrder->id,
                'tipo' => 'trabajo_completado',
                'user_id' => $request->user()->id,
                'payload' => [
                    'accion' => 'mantenimiento_campo_finalizado',
                    'responsable_nombre' => $request->user()->name,
                    'conformidad_nombre' => $validated['conformidad_nombre'],
                    'receptor_nombre' => $validated['conformidad_nombre'],
                    'observaciones_entrega' => $validated['observaciones_generales'] ?? null,
                    'eslabon_custodia' => 'mantenimiento_campo',
                    'fecha_finalizado' => now()->toIso8601String(),
                    'equipos_atendidos_count' => $serviceOrder->equipments()->count(),
                ],
            ]);

            $pendientes = $serviceOrder->deficiencies()
                ->where('requiere_autorizacion', true)
                ->where('estado', 'esperando_autorizacion')
                ->exists();

            if ($pendientes) {
                $serviceOrder->update(['estado' => 'esperando_autorizacion']);

                return 'Mantenimiento finalizado. Hay deficiencias que requieren autorización de ventas.';
            }

            $serviceOrder->update(['estado' => 'listo_entrega']);

            return $this->emitirCertificado($serviceOrder)
                ? 'Mantenimiento finalizado y certificado emitido para los extintores conformes.'
                : 'Mantenimiento finalizado. Ningún extintor salió conforme: no se emitió certificado.';
        });

        return back()->with('success', $mensaje);
    }

    public function pdf(Team $current_team, ServiceOrder $serviceOrder, ActaConformidadPdfService $pdfService): HttpResponse
    {
        abort_unless($this->finalizado($serviceOrder), 404);

        return $pdfService->generate($serviceOrder)->stream("acta-mantenimiento-{$serviceOrder->codigo}.pdf");
    }

    /**
     * El certificado solo lleva los extintores cuyo último checklist salió
     * conforme. Una sola consulta con subconsulta correlacionada (B5), no una
     * por equipo.
     */
    protected function emitirCertificado(ServiceOrder $serviceOrder): bool
    {
        $conformes = $this->equiposConformes($serviceOrder);

        $tipo = CertificateType::where('codigo', 'operatividad_garantia')->first();

        if (! $tipo || $conformes->isEmpty()) {
            return false;
        }

        $serviceOrder->loadMissing('client');

        app(IssueCertificate::class)->handle(
            $tipo,
            $serviceOrder->client,
            $conformes->map(fn (Equipment $eq) => ['equipment_id' => $eq->id, 'numero_serie' => $eq->numero_serie])->all(),
            $serviceOrder->sale_id,
            $serviceOrder->id,
            ['tipo_atencion' => 'mantenimiento'],
        );

        return true;
    }

    protected function finalizado(ServiceOrder $serviceOrder): bool
    {
        return $serviceOrder->events()->where('payload->accion', 'mantenimiento_campo_finalizado')->exists();
    }

    /**
     * Extintores aptos para certificar: los de la orden que no están
     * descargados y cuyo último checklist de esta orden salió conforme (B5).
     *
     * @return EloquentCollection<int, Equipment>
     */
    protected function equiposConformes(ServiceOrder $serviceOrder): EloquentCollection
    {
        return $serviceOrder->equipments()
            ->where('equipment.estado', '!=', 'descargado')
            ->whereRaw(
                "(select c.resultado_general from technical_checklists c where c.service_order_id = ? and c.equipment_id = equipment.id order by c.id desc limit 1) = 'conforme'",
                [$serviceOrder->id],
            )
            ->get();
    }

    /**
     * Órdenes de mantenimiento: las de campo cuyo servicio se llama así.
     *
     * @return Builder<ServiceOrder>
     */
    protected function ordenes(Request $request)
    {
        return ServiceOrder::query()
            ->accessibleToTechnician($request->user())
            ->where('departamento_tecnico', 'campo')
            ->whereHas('service', fn ($service) => $service->where('nombre', 'like', '%mantenim%'));
    }
}
