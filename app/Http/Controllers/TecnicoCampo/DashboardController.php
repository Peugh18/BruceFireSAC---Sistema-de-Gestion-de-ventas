<?php

namespace App\Http\Controllers\TecnicoCampo;

use App\Http\Controllers\Controller;
use App\Models\ServiceOrder;
use App\Models\Team;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Dashboard operativo del Técnico de Campo (§5.5, §85.4).
     * Muestra los servicios del día: recojos, entregas, inspecciones, instalaciones.
     * Mobile-first con tarjetas táctiles y colas por estado.
     */
    public function index(Request $request, Team $current_team): Response
    {
        $fecha = $request->string('fecha', now()->toDateString())->toString();
        $statusTab = $request->string('tab', 'todos')->toString(); // todos | pendientes | en_proceso | finalizados
        $tipoFiltro = $request->string('tipo', 'todos')->toString(); // todos | recojos | entregas | inspecciones | instalaciones
        $search = trim((string) $request->input('search', ''));

        // Query base para órdenes de Campo
        $query = ServiceOrder::query()
            ->accessibleToTechnician($request->user())
            ->where(function ($q) {
                $q->where('departamento_tecnico', 'campo')
                    ->orWhereNull('departamento_tecnico');
            })
            ->with([
                'client:id,nombre_comercial,razon_social,telefono,numero_documento,direccion_fiscal',
                'sede:id,nombre',
                'vehicle:id,placa,marca,modelo',
                'equipments:id,numero_serie,tipo_agente',
            ])
            ->latest('id');

        // Búsqueda rápida
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('codigo', 'like', "%{$search}%")
                    ->orWhereHas('client', function ($cq) use ($search) {
                        $cq->where('nombre_comercial', 'like', "%{$search}%")
                            ->orWhere('razon_social', 'like', "%{$search}%")
                            ->orWhere('numero_documento', 'like', "%{$search}%")
                            ->orWhere('direccion_fiscal', 'like', "%{$search}%");
                    });
            });
        }

        // Filtro por Estado Operativo (§5.5)
        match ($statusTab) {
            'pendientes' => $query->whereIn('estado', ['pendiente_recepcion', 'recibido_planta', 'en_revision', 'listo_entrega']),
            'en_proceso' => $query->whereIn('estado', ['en_proceso', 'esperando_autorizacion', 'autorizado']),
            'finalizados' => $query->whereIn('estado', ['trabajo_terminado', 'entregado', 'cerrado']),
            default => null,
        };

        // Filtro por Tipo de Servicio (§5.5)
        if ($tipoFiltro !== 'todos') {
            match ($tipoFiltro) {
                'recojos' => $query->where(fn ($q) => $q->where('tipo_servicio', 'like', '%recojo%')->orWhere('estado', 'pendiente_recepcion')),
                'entregas' => $query->where(fn ($q) => $q->where('tipo_servicio', 'like', '%entrega%')->orWhereIn('estado', ['listo_entrega', 'entregado'])),
                'inspecciones' => $query->where('tipo_servicio', 'like', '%inspecci%'),
                'instalaciones' => $query->where('tipo_servicio', 'like', '%instalac%'),
                default => null,
            };
        }

        $orders = $query->paginate(15)->through(function (ServiceOrder $order) {
            // Determinar acción sugerida en campo
            $accionSugerida = match (true) {
                $order->estado === 'pendiente_recepcion' => 'recojo',
                $order->estado === 'listo_entrega' || $order->estado === 'entregado' => 'entrega',
                str_contains(strtolower($order->tipo_servicio), 'inspecci') => 'inspeccion',
                str_contains(strtolower($order->tipo_servicio), 'instalac') => 'instalacion',
                default => 'ver',
            };

            return [
                'id' => $order->id,
                'codigo' => $order->codigo,
                'cliente' => $order->client->nombre_comercial ?: $order->client->razon_social,
                'cliente_doc' => $order->client->numero_documento,
                'telefono' => $order->client->telefono,
                'direccion' => $order->client->direccion_fiscal,
                'sede' => $order->sede?->nombre,
                'tipo_servicio' => $order->tipo_servicio,
                'fecha' => $order->fecha->toDateString(),
                'prioridad' => $order->prioridad,
                'estado' => $order->estado,
                'estado_coarse' => $order->coarseLabel(),
                'vehiculo' => $order->vehicle ? "{$order->vehicle->marca} {$order->vehicle->modelo} ({$order->vehicle->placa})" : null,
                'equipos_count' => $order->equipments->count(),
                'accion_sugerida' => $accionSugerida,
                'observaciones' => $order->observaciones,
            ];
        });

        // KPIs operativos de Campo (§5.5)
        $kpis = [
            'total_servicios' => ServiceOrder::where(fn ($q) => $q->where('departamento_tecnico', 'campo')->orWhereNull('departamento_tecnico'))
                ->where('estado', '!=', 'cerrado')
                ->count(),
            'pendientes' => ServiceOrder::where(fn ($q) => $q->where('departamento_tecnico', 'campo')->orWhereNull('departamento_tecnico'))
                ->whereIn('estado', ['pendiente_recepcion', 'recibido_planta', 'en_revision', 'listo_entrega'])
                ->count(),
            'en_proceso' => ServiceOrder::where(fn ($q) => $q->where('departamento_tecnico', 'campo')->orWhereNull('departamento_tecnico'))
                ->whereIn('estado', ['en_proceso', 'esperando_autorizacion', 'autorizado'])
                ->count(),
            'finalizados' => ServiceOrder::where(fn ($q) => $q->where('departamento_tecnico', 'campo')->orWhereNull('departamento_tecnico'))
                ->whereIn('estado', ['trabajo_terminado', 'entregado', 'cerrado'])
                ->count(),
        ];

        return Inertia::render('tecnico-campo/dashboard', [
            'orders' => $orders,
            'kpis' => $kpis,
            'filters' => [
                'fecha' => $fecha,
                'tab' => $statusTab,
                'tipo' => $tipoFiltro,
                'search' => $search,
            ],
        ]);
    }
}
