<?php

namespace App\Http\Controllers\TecnicoPlanta;

use App\Actions\TecnicoPlanta\ExecuteAndCloseServiceOrder;
use App\Http\Controllers\Controller;
use App\Models\Deficiency;
use App\Models\ServiceOrder;
use App\Models\ServiceOrderEvent;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DeficiencyController extends Controller
{
    /**
     * Listado de deficiencias detectadas en Planta (§20).
     */
    public function index(Request $request, Team $current_team): Response
    {
        $estado = $request->string('estado', 'todas')->toString();
        $search = trim((string) $request->input('search', ''));

        $query = Deficiency::query()
            ->with([
                'serviceOrder.client:id,nombre_comercial,razon_social,telefono',
                'serviceOrder.sede:id,nombre',
                'equipment:id,numero_serie,tipo_agente,capacidad,marca',
                'authorization.vendedor:id,name',
            ])
            ->whereHas('serviceOrder', function ($q) {
                $q->where('departamento_tecnico', 'planta')
                    ->orWhereNull('departamento_tecnico');
            })
            ->whereHas('serviceOrder', fn ($query) => $query->accessibleToTechnician($request->user()))
            ->latest('id');

        if ($estado !== 'todas' && $estado !== '') {
            $query->where('estado', $estado);
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('componente', 'like', "%{$search}%")
                    ->orWhere('condicion', 'like', "%{$search}%")
                    ->orWhere('repuesto_sugerido', 'like', "%{$search}%")
                    ->orWhereHas('serviceOrder', fn ($sq) => $sq->where('codigo', 'like', "%{$search}%"))
                    ->orWhereHas('equipment', fn ($eq) => $eq->where('numero_serie', 'like', "%{$search}%"));
            });
        }

        $deficiencies = $query->paginate(15)->withQueryString()->through(function (Deficiency $d) {
            return [
                'id' => $d->id,
                'orden_id' => $d->service_order_id,
                'orden_codigo' => $d->serviceOrder->codigo,
                'cliente' => $d->serviceOrder->client->nombre_comercial ?: $d->serviceOrder->client->razon_social,
                'equipment_id' => $d->equipment_id,
                'equipo_serie' => $d->equipment?->numero_serie,
                'equipo_tipo' => $d->equipment?->tipo_agente,
                'componente' => $d->componente,
                'condicion' => $d->condicion,
                'nota' => $d->nota,
                'accion_recomendada' => $d->accion_recomendada,
                'repuesto_sugerido' => $d->repuesto_sugerido,
                'requiere_autorizacion' => $d->requiere_autorizacion,
                'estado' => $d->estado,
                'resolucion' => $d->resolucion,
                'authorization' => $d->authorization ? [
                    'autorizado_por' => $d->authorization->autorizado_por,
                    'canal' => $d->authorization->canal,
                    'fecha' => $d->authorization->fecha->toDateString(),
                    'observacion' => $d->authorization->observacion,
                    'vendedor' => $d->authorization->vendedor?->name,
                ] : null,
                'created_at' => $d->created_at?->toDateString(),
            ];
        });

        // Contadores por estado
        $counts = [
            'todas' => Deficiency::whereHas('serviceOrder', fn ($q) => $q->where('departamento_tecnico', 'planta')->orWhereNull('departamento_tecnico'))->count(),
            'esperando_autorizacion' => Deficiency::whereHas('serviceOrder', fn ($q) => $q->where('departamento_tecnico', 'planta')->orWhereNull('departamento_tecnico'))->where('estado', 'esperando_autorizacion')->count(),
            'autorizada' => Deficiency::whereHas('serviceOrder', fn ($q) => $q->where('departamento_tecnico', 'planta')->orWhereNull('departamento_tecnico'))->where('estado', 'autorizada')->count(),
            'rechazada' => Deficiency::whereHas('serviceOrder', fn ($q) => $q->where('departamento_tecnico', 'planta')->orWhereNull('departamento_tecnico'))->where('estado', 'rechazada')->count(),
            'resuelta' => Deficiency::whereHas('serviceOrder', fn ($q) => $q->where('departamento_tecnico', 'planta')->orWhereNull('departamento_tecnico'))->where('estado', 'resuelta')->count(),
        ];

        return Inertia::render('tecnico-planta/deficiencias/index', [
            'deficiencies' => $deficiencies,
            'counts' => $counts,
            'filters' => [
                'estado' => $estado,
                'search' => $search,
            ],
        ]);
    }

    /**
     * Registra una deficiencia desde Planta (§20) con notificación a Vendedor si requiere autorización.
     */
    public function store(
        Request $request,
        Team $current_team,
        ServiceOrder $service_order
    ): RedirectResponse {
        $validated = $request->validate([
            'equipment_id' => ['nullable', 'exists:equipment,id'],
            'componente' => ['required', 'string', 'max:150'],
            'condicion' => ['required', 'string', 'max:255'],
            'nota' => ['nullable', 'string', 'max:500'],
            'accion_recomendada' => ['nullable', 'string', 'max:255'],
            'repuesto_sugerido' => ['nullable', 'string', 'max:255'],
            'requiere_autorizacion' => ['nullable', 'boolean'],
            'foto_path' => ['nullable', 'string', 'max:255'],
        ]);

        $requiereAuth = (bool) ($validated['requiere_autorizacion'] ?? false);
        $equipmentId = $validated['equipment_id'] ?? $service_order->equipments()->value('equipment.id');

        $deficiency = Deficiency::create([
            'service_order_id' => $service_order->id,
            'equipment_id' => $equipmentId,
            'componente' => $validated['componente'],
            'condicion' => $validated['condicion'],
            'nota' => $validated['nota'] ?? null,
            'accion_recomendada' => $validated['accion_recomendada'] ?? null,
            'repuesto_sugerido' => $validated['repuesto_sugerido'] ?? null,
            'requiere_autorizacion' => $requiereAuth,
            'estado' => $requiereAuth ? 'esperando_autorizacion' : 'detectada',
            'reported_by_user_id' => $request->user()->id,
        ]);

        if ($requiereAuth) {
            $service_order->update(['estado' => 'esperando_autorizacion']);

            // Evento inmutable de bitácora
            ServiceOrderEvent::create([
                'service_order_id' => $service_order->id,
                'tipo' => 'deficiencia_detectada',
                'user_id' => $request->user()->id,
                'payload' => [
                    'deficiency_id' => $deficiency->id,
                    'componente' => $deficiency->componente,
                    'condicion' => $deficiency->condicion,
                    'accion_recomendada' => $deficiency->accion_recomendada,
                    'repuesto_sugerido' => $deficiency->repuesto_sugerido,
                    'requiere_autorizacion' => true,
                ],
            ]);

            // Notificación a Vendedor (§17, §20)
            ServiceOrderEvent::create([
                'service_order_id' => $service_order->id,
                'tipo' => 'notificacion_vendedor',
                'user_id' => $request->user()->id,
                'payload' => [
                    'mensaje' => sprintf(
                        'Deficiencia en %s (%s): %s. Requiere autorización comercial.',
                        $service_order->codigo,
                        $deficiency->componente,
                        $deficiency->condicion
                    ),
                    'deficiency_id' => $deficiency->id,
                ],
            ]);
        } else {
            ServiceOrderEvent::create([
                'service_order_id' => $service_order->id,
                'tipo' => 'deficiencia_detectada',
                'user_id' => $request->user()->id,
                'payload' => [
                    'deficiency_id' => $deficiency->id,
                    'componente' => $deficiency->componente,
                    'condicion' => $deficiency->condicion,
                    'requiere_autorizacion' => false,
                ],
            ]);
        }

        return back()->with('success', 'Deficiencia registrada con éxito.');
    }

    /**
     * Marca una deficiencia como resuelta en taller (§20, §85).
     */
    public function resolve(
        Request $request,
        Team $current_team,
        Deficiency $deficiency,
        ExecuteAndCloseServiceOrder $action
    ): RedirectResponse {
        // Si sugiere un repuesto físico, debe resolverse desde Ejecución para
        // generar el InventoryMovement real contra el Kardex de Almacén (§85,
        // Fase 5) — este endpoint es solo para deficiencias sin repuesto.
        if (filled($deficiency->repuesto_sugerido)) {
            return back()->withErrors([
                'resolucion' => 'Esta deficiencia sugiere un repuesto físico. Resuélvela desde la pantalla de Ejecución para descontar el stock de Almacén correctamente.',
            ]);
        }

        $validated = $request->validate([
            'resolucion' => ['required', 'string', 'max:500'],
        ]);

        $deficiency->update([
            'estado' => 'resuelta',
            'resolucion' => $validated['resolucion'],
        ]);

        ServiceOrderEvent::create([
            'service_order_id' => $deficiency->service_order_id,
            'tipo' => 'otro',
            'user_id' => $request->user()->id,
            'payload' => [
                'accion' => 'deficiencia_resuelta',
                'deficiency_id' => $deficiency->id,
                'componente' => $deficiency->componente,
                'resolucion' => $validated['resolucion'],
            ],
        ]);

        // Si esta era la última deficiencia pendiente de autorización, la
        // orden no debe quedar bloqueada en esperando_autorizacion (§17, §85).
        $action->releaseFromAuthorizationHold($deficiency->serviceOrder, $request->user());

        return back()->with('success', 'Deficiencia marcada como resuelta.');
    }
}
