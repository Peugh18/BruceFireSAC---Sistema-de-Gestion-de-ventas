<?php

namespace App\Http\Controllers\TecnicoPlanta;

use App\Http\Controllers\Controller;
use App\Models\ServiceOrder;
use App\Models\Team;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Dashboard operativo del Técnico de Planta (§5.4, §85.4).
     * Visualización mobile-first de colas por estado, KPIs táctiles y órdenes de taller.
     */
    public function index(Request $request, Team $current_team): Response
    {
        $tab = $request->string('tab', 'todas')->toString();
        $search = trim((string) $request->input('search', ''));

        // Query base para órdenes de Planta
        $query = ServiceOrder::query()
            ->accessibleToTechnician($request->user())
            ->where(function ($q) {
                $q->where('departamento_tecnico', 'planta')
                    ->orWhereNull('departamento_tecnico');
            })
            ->where('estado', '!=', 'cerrado')
            ->with([
                'client:id,nombre_comercial,razon_social,telefono,numero_documento',
                'sede:id,nombre,ubigeo', 'sede.ubicacion',
                'deficiencies:id,service_order_id,componente,estado,requiere_autorizacion',
                'events' => fn ($q) => $q->latest('id')->limit(1),
            ])
            ->latest('id');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('codigo', 'like', "%{$search}%")
                    ->orWhereHas('client', function ($cq) use ($search) {
                        $cq->where('nombre_comercial', 'like', "%{$search}%")
                            ->orWhere('razon_social', 'like', "%{$search}%")
                            ->orWhere('numero_documento', 'like', "%{$search}%");
                    });
            });
        }

        // Filtro de colas (§5.4)
        match ($tab) {
            'pendientes' => $query->where('estado', 'pendiente_recepcion'),
            'en_taller' => $query->whereIn('estado', ['recibido_planta', 'en_proceso', 'autorizado']),
            'esperando_autorizacion' => $query->where('estado', 'esperando_autorizacion'),
            'por_entregar' => $query->whereIn('estado', ['trabajo_terminado', 'pendiente_datos', 'datos_completos', 'listo_certificado', 'listo_entrega', 'entregado']),
            default => null,
        };

        $orders = $query->paginate(15)->through(function (ServiceOrder $order) {
            $deficienciesCount = $order->deficiencies->count();
            $pendingAuthCount = $order->deficiencies->where('requiere_autorizacion', true)->where('estado', 'pendiente')->count();

            return [
                'id' => $order->id,
                'codigo' => $order->codigo,
                'cliente' => $order->client->nombre_comercial ?: $order->client->razon_social,
                'cliente_doc' => $order->client->numero_documento,
                'telefono' => $order->client->telefono,
                'sede' => $order->sede?->nombre,
                'tipo_servicio' => $order->service->nombre,
                'fecha' => $order->fecha->toDateString(),
                'prioridad' => $order->prioridad,
                'estado' => $order->estado,
                'estado_coarse' => $order->coarseLabel(),
                'observaciones' => $order->observaciones,
                'deficiencias_count' => $deficienciesCount,
                'requiere_autorizacion_count' => $pendingAuthCount,
                'ultimo_evento' => $order->events->first()?->tipo,
            ];
        });

        // KPIs operativos de Planta (§5.4)
        $kpis = [
            'pendientes_recepcion' => ServiceOrder::where(fn ($q) => $q->where('departamento_tecnico', 'planta')->orWhereNull('departamento_tecnico'))
                ->where('estado', 'pendiente_recepcion')
                ->count(),
            'en_taller' => ServiceOrder::where(fn ($q) => $q->where('departamento_tecnico', 'planta')->orWhereNull('departamento_tecnico'))
                ->whereIn('estado', ['recibido_planta', 'en_proceso', 'autorizado'])
                ->count(),
            'esperando_autorizacion' => ServiceOrder::where(fn ($q) => $q->where('departamento_tecnico', 'planta')->orWhereNull('departamento_tecnico'))
                ->where('estado', 'esperando_autorizacion')
                ->count(),
            'listas' => ServiceOrder::where(fn ($q) => $q->where('departamento_tecnico', 'planta')->orWhereNull('departamento_tecnico'))
                ->whereIn('estado', ['listo_certificado', 'listo_entrega', 'trabajo_terminado'])
                ->count(),
        ];

        return Inertia::render('tecnico-planta/dashboard', [
            'orders' => $orders,
            'kpis' => $kpis,
            'filters' => [
                'tab' => $tab,
                'search' => $search,
            ],
        ]);
    }
}
