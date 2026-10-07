<?php

namespace App\Http\Controllers\TecnicoCampo;

use App\Actions\Equipment\RenewEquipmentAttentionDate;
use App\Actions\Tecnico\GuardarEvidencia;
use App\Http\Controllers\Controller;
use App\Models\ServiceOrder;
use App\Models\ServiceOrderEvent;
use App\Models\Team;
use App\Services\Reports\ActaConformidadPdfService;
use App\Services\Tecnico\ConversacionDeLaOrden;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;

class DeliveryController extends Controller
{
    /**
     * Listado de entregas y cierres de servicio en campo (§22.3, §23).
     */
    public function index(Request $request, Team $current_team): Response
    {
        $tab = $request->query('tab', 'listas');
        $search = $request->query('q');

        $query = ServiceOrder::query()
            ->accessibleToTechnician($request->user())
            ->with(['client', 'sede', 'equipments'])
            ->where(function ($q) {
                $q->where('departamento_tecnico', 'campo')
                    ->orWhereIn('estado', ['listo_entrega', 'entregado', 'cerrado']);
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

        if ($tab === 'listas') {
            $query->where('estado', 'listo_entrega');
        } elseif ($tab === 'entregadas') {
            $query->where('estado', 'entregado');
        } elseif ($tab === 'cerradas') {
            $query->where('estado', 'cerrado');
        }

        $entregas = $query->paginate(15)->withQueryString();

        $baseStatsQuery = ServiceOrder::query()
            ->accessibleToTechnician($request->user())
            ->where(function ($q) {
                $q->where('departamento_tecnico', 'campo')
                    ->orWhereIn('estado', ['listo_entrega', 'entregado', 'cerrado']);
            });

        $stats = [
            'total' => (clone $baseStatsQuery)->whereIn('estado', ['listo_entrega', 'entregado', 'cerrado'])->count(),
            'listas' => (clone $baseStatsQuery)->where('estado', 'listo_entrega')->count(),
            'entregadas' => (clone $baseStatsQuery)->where('estado', 'entregado')->count(),
            'cerradas' => (clone $baseStatsQuery)->where('estado', 'cerrado')->count(),
        ];

        return Inertia::render('tecnico-campo/entregas/index', [
            'entregas' => $entregas,
            'currentTab' => $tab,
            'search' => $search,
            'stats' => $stats,
        ]);
    }

    /**
     * Vista de detalle de entrega con historial de custodia y confirmación en sitio (§22.3, §23).
     */
    public function show(Team $current_team, ServiceOrder $serviceOrder): Response
    {
        $serviceOrder->load([
            'client',
            'sede',
            'vehicle',
            'equipments',
            'events' => fn ($q) => $q->latest('created_at'),
            'certificates.certificateType',
        ]);

        $custodyEvents = $serviceOrder->events
            ->filter(fn ($e) => isset($e->payload['eslabon_custodia']))
            ->values()
            ->map(fn ($e) => [
                'id' => $e->id,
                'eslabon' => $e->payload['eslabon_custodia'] ?? '',
                'responsable' => $e->payload['responsable_nombre'] ?? $e->user?->name,
                'fecha' => $e->created_at?->toIso8601String(),
                'payload' => $e->payload,
            ]);

        return Inertia::render('tecnico-campo/entregas/show', [
            'asignacion' => $serviceOrder->asignacionPara(request()->user()),
            'conversacion' => app(ConversacionDeLaOrden::class)->paraPagina($serviceOrder),
            'evidencias' => $serviceOrder->evidencias()->where('etapa', 'entrega')->get(['id', 'etapa', 'tipo', 'equipment_id']),
            'order' => $serviceOrder,
            'custodyEvents' => $custodyEvents,
        ]);
    }

    /**
     * Confirma la entrega final del servicio al cliente y cierra la orden (§22.3, §23, §85.6.2).
     */
    public function confirm(
        Request $request,
        Team $current_team,
        ServiceOrder $serviceOrder,
        RenewEquipmentAttentionDate $renewEquipmentAttentionDate,
    ): RedirectResponse {
        // Se entrega una sola vez y solo lo que ya está listo.
        abort_unless(in_array($serviceOrder->estado, ServiceOrder::ESTADOS_LISTOS, true), 422, 'La orden todavía no está lista para entregar.');
        abort_if($serviceOrder->events()->where('payload->accion', 'entrega_final_realizada')->exists(), 422, 'Esta orden ya se entregó.');

        $validated = $request->validate([
            'receptor_nombre' => ['required', 'string', 'max:150'],
            'receptor_dni' => ['nullable', 'string', 'max:20'],
            'observaciones_entrega' => ['nullable', 'string', 'max:1000'],
            'conformidad_aceptada' => ['required', 'accepted'],
            'cerrar_orden' => ['nullable', 'boolean'],
            'firma' => ['nullable', 'string', 'max:1500000', 'starts_with:data:image/png;base64,'],
        ]);

        if (filled($validated['firma'] ?? null)) {
            app(GuardarEvidencia::class)->firma($serviceOrder, $validated['firma'], 'entrega', $request->user());
        }

        $yaRenovadas = $serviceOrder->events()->where('tipo', 'trabajo_completado')->exists();

        // Registrar eslabón final de custodia (§22.4, §85.6.3)
        ServiceOrderEvent::create([
            'service_order_id' => $serviceOrder->id,
            'tipo' => 'trabajo_completado',
            'user_id' => $request->user()->id,
            'payload' => [
                'accion' => 'entrega_final_realizada',
                'eslabon_custodia' => 'entrega_campo',
                'responsable_nombre' => $request->user()->name,
                'receptor_nombre' => $validated['receptor_nombre'],
                'receptor_dni' => $validated['receptor_dni'] ?? null,
                'observaciones_entrega' => $validated['observaciones_entrega'] ?? null,
                'fecha_entrega' => now()->toIso8601String(),
                'equipos_entregados_count' => $serviceOrder->equipments()->count(),
            ],
        ]);

        $debeCerrar = $request->boolean('cerrar_orden', true);
        $serviceOrder->update([
            'estado' => $debeCerrar ? 'cerrado' : 'entregado',
        ]);

        // Si el trabajo ya se registró (certificado del taller, inspección o
        // instalación), las fechas ya quedaron como corresponden: entregar no
        // las vuelve a mover. Un extintor descargado sigue avisando.
        if ($debeCerrar && ! $yaRenovadas) {
            $phRealizada = $serviceOrder->certificates()->whereHas('certificateType', fn ($q) => $q->where('codigo', 'prueba_hidrostatica'))->exists();
            $renewEquipmentAttentionDate->execute(
                $serviceOrder->equipments()->whereNotIn('equipment.estado', ['descargado', 'usado'])->get(),
                $phRealizada,
            );
        }

        $msg = $debeCerrar
            ? 'Entrega final completada y orden de servicio cerrada exitosamente.'
            : 'Entrega final completada con éxito.';

        return redirect()
            ->route('tecnico-campo.entregas.show', [
                'current_team' => $current_team,
                'service_order' => $serviceOrder->id,
            ])
            ->with('success', $msg);
    }

    /**
     * Descarga / previsualización del PDF del Acta de Conformidad (§23).
     */
    public function pdf(
        Team $current_team,
        ServiceOrder $serviceOrder,
        ActaConformidadPdfService $pdfService
    ): HttpResponse {
        // El acta existe solo después de la entrega; entregar ya exige la
        // conformidad del cliente, así que basta con que la entrega exista.
        abort_unless($serviceOrder->events()
            ->where('payload->accion', 'entrega_final_realizada')
            ->exists(), 404);

        $dompdf = $pdfService->generate($serviceOrder);

        return $dompdf->stream("acta-conformidad-{$serviceOrder->codigo}.pdf");
    }
}
