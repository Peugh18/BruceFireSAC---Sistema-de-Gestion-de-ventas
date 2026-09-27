<?php

namespace App\Http\Controllers\TecnicoCampo;

use App\Actions\TecnicoCampo\RegisterCollection;
use App\Http\Controllers\Controller;
use App\Models\ServiceOrder;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CollectionController extends Controller
{
    /**
     * Lista de recojos programados para el Técnico de Campo (§22.1).
     */
    public function index(Request $request, Team $current_team): Response
    {
        $search = trim((string) $request->input('search', ''));

        $query = ServiceOrder::query()
            ->accessibleToTechnician($request->user())
            ->where(function ($q) {
                $q->where('departamento_tecnico', 'campo')
                    ->orWhereNull('departamento_tecnico');
            })
            ->where(function ($q) {
                $q->where('tipo_servicio', 'like', '%recojo%')
                    ->orWhere('estado', 'pendiente_recepcion');
            })
            ->with([
                'client:id,nombre_comercial,razon_social,telefono,numero_documento,direccion_fiscal',
                'vehicle:id,placa,marca',
                'equipments',
            ])
            ->latest('id');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('codigo', 'like', "%{$search}%")
                    ->orWhereHas('client', function ($cq) use ($search) {
                        $cq->where('nombre_comercial', 'like', "%{$search}%")
                            ->orWhere('razon_social', 'like', "%{$search}%")
                            ->orWhere('direccion_fiscal', 'like', "%{$search}%");
                    });
            });
        }

        $recojos = $query->paginate(15)->through(function (ServiceOrder $order) {
            $custodyEvent = $order->events()
                ->where('payload->eslabon_custodia', 'recojo_campo')
                ->latest('created_at')
                ->first();

            return [
                'id' => $order->id,
                'codigo' => $order->codigo,
                'cliente' => $order->client->nombre_comercial ?: $order->client->razon_social,
                'direccion' => $order->client->direccion_fiscal,
                'telefono' => $order->client->telefono,
                'fecha' => $order->fecha->toDateString(),
                'prioridad' => $order->prioridad,
                'estado' => $order->estado,
                'vehiculo' => $order->vehicle?->placa,
                'equipos_count' => $order->equipments->count(),
                'ya_recogido' => $custodyEvent !== null,
                'recogido_at' => $custodyEvent?->created_at?->toIso8601String(),
            ];
        });

        return Inertia::render('tecnico-campo/recojos/index', [
            'recojos' => $recojos,
            'filters' => ['search' => $search],
        ]);
    }

    /**
     * Vista de detalle del recojo físico con cadena de custodia (§22.1, §22.4).
     */
    public function show(Team $current_team, ServiceOrder $service_order): Response
    {
        $service_order->load([
            'client',
            'sede',
            'vehicle',
            'equipments',
            'events' => fn ($q) => $q->latest('created_at'),
        ]);

        $custodyEvents = $service_order->events
            ->filter(fn ($e) => isset($e->payload['eslabon_custodia']))
            ->values()
            ->map(fn ($e) => [
                'id' => $e->id,
                'eslabon' => $e->payload['eslabon_custodia'] ?? '',
                'etapa' => $e->payload['etapa'] ?? '',
                'responsable' => $e->payload['responsable_nombre'] ?? $e->user?->name,
                'fecha' => $e->created_at?->toIso8601String(),
                'payload' => $e->payload,
            ]);

        return Inertia::render('tecnico-campo/recojos/show', [
            'asignacion' => $service_order->asignacionPara(request()->user()),
            'order' => [
                'id' => $service_order->id,
                'codigo' => $service_order->codigo,
                'cliente' => [
                    'nombre' => $service_order->client->nombre_comercial ?: $service_order->client->razon_social,
                    'documento' => $service_order->client->numero_documento,
                    'direccion' => $service_order->client->direccion_fiscal,
                    'telefono' => $service_order->client->telefono,
                ],
                'vehiculo' => $service_order->vehicle ? "{$service_order->vehicle->marca} {$service_order->vehicle->modelo} ({$service_order->vehicle->placa})" : null,
                'tipo_servicio' => $service_order->tipo_servicio,
                'fecha' => $service_order->fecha->toDateString(),
                'prioridad' => $service_order->prioridad,
                'estado' => $service_order->estado,
                'observaciones' => $service_order->observaciones,
                'notas_vendedor' => $service_order->events
                    ->filter(fn ($e) => ($e->payload['origen'] ?? null) === 'vendedor' || $e->tipo === 'notificacion_vendedor' || ($e->tipo === 'otro' && isset($e->payload['mensaje'])))
                    ->values()
                    ->map(fn ($e) => [
                        'id' => $e->id,
                        'mensaje' => $e->payload['mensaje'] ?? $e->payload['descripcion'] ?? '',
                        'fecha' => $e->created_at?->toIso8601String(),
                    ]),
                'equipments' => $service_order->equipments->map(fn ($eq) => [
                    'id' => $eq->id,
                    'numero_serie' => $eq->numero_serie,
                    'tipo_agente' => $eq->tipo_agente,
                    'capacidad' => $eq->capacidad,
                    'marca' => $eq->marca,
                ]),
            ],
            'custodyEvents' => $custodyEvents,
        ]);
    }

    /**
     * Guarda el registro de recojo físico y añade el evento de cadena de custodia.
     */
    public function store(
        Request $request,
        Team $current_team,
        ServiceOrder $service_order,
        RegisterCollection $action
    ): RedirectResponse {
        $validated = $request->validate([
            'cantidad' => ['required', 'integer', 'min:1'],
            'contacto_nombre' => ['required', 'string', 'max:150'],
            'contacto_telefono' => ['nullable', 'string', 'max:50'],
            'observaciones' => ['nullable', 'string', 'max:1000'],
            'conformidad_cliente' => ['required', 'accepted'],
            'foto_path' => ['nullable', 'string', 'max:255'],
        ]);

        $action->execute($service_order, $request->user(), [
            'cantidad' => (int) $validated['cantidad'],
            'contacto_nombre' => $validated['contacto_nombre'],
            'contacto_telefono' => $validated['contacto_telefono'] ?? null,
            'observaciones' => $validated['observaciones'] ?? null,
            'conformidad_cliente' => true,
            'foto_path' => $validated['foto_path'] ?? null,
        ]);

        return back()->with('success', 'Recojo y cadena de custodia registrados exitosamente.');
    }
}
