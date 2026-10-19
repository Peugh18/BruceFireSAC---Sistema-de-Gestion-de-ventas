<?php

namespace App\Http\Controllers\TecnicoCampo;

use App\Http\Controllers\Controller;
use App\Models\Sede;
use App\Models\ServiceOrder;
use App\Models\ServiceOrderEvent;
use App\Models\Team;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
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
        $request->validate(['fecha' => ['nullable', 'date_format:Y-m-d']]);
        $hoy = now('America/Lima')->toDateString();
        $fecha = $request->input('fecha') ?: $hoy;
        $finalizados = ['trabajo_terminado', 'entregado', 'cerrado'];
        $statusTab = $request->string('tab', 'todos')->toString(); // todos | pendientes | en_proceso | finalizados
        $tipoFiltro = $request->string('tipo', 'todos')->toString(); // todos | recojos | entregas | inspecciones | instalaciones
        $search = trim((string) $request->input('search', ''));

        // Query base para órdenes de Campo
        $query = ServiceOrder::query()
            ->accessibleToTechnician($request->user())
            ->where(function ($q) {
                $q->where('departamento_tecnico', 'campo')
                    ->orWhereNull('departamento_tecnico')
                    ->orWhereIn('estado', [...ServiceOrder::ESTADOS_LISTOS, 'entregado', 'cerrado']);
            })
            ->where('estado', '!=', 'anulada')
            ->with([
                'client:id,nombre_comercial,razon_social,telefono,numero_documento,direccion_fiscal',
                'service:id,nombre',
                'lines.service:id,nombre',
                'sede:id,nombre',
                'vehicle:id,placa,marca,modelo',
                'equipments:id,numero_serie,tipo_agente',
            ])
            ->whereDate('fecha', $fecha === $hoy ? '<=' : '=', $fecha)
            ->orderByRaw("CASE prioridad WHEN 'urgente' THEN 0 WHEN 'alta' THEN 1 ELSE 2 END")
            ->orderBy('fecha')
            ->orderBy('id');

        $base = clone $query;
        if ($statusTab !== 'finalizados') {
            $query->whereNotIn('estado', $finalizados);
        }

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
            'pendientes' => $query->whereIn('estado', ['pendiente_recepcion', 'recibido_planta', ...ServiceOrder::ESTADOS_LISTOS]),
            'en_proceso' => $query->whereIn('estado', ['en_proceso', 'esperando_autorizacion', 'autorizado']),
            'finalizados' => $query->whereIn('estado', $finalizados),
            default => null,
        };

        // Filtro por Tipo de Servicio (§5.5)
        if ($tipoFiltro !== 'todos') {
            match ($tipoFiltro) {
                'recojos' => $query->where(fn ($q) => $q->whereHas('service', fn ($service) => $service->where('nombre', 'like', '%recojo%'))
                    ->orWhere(fn (Builder $pendiente) => $pendiente->where('estado', 'pendiente_recepcion')
                        ->whereDoesntHave('service', fn ($service) => $service->where(fn ($s) => $s->where('nombre', 'like', '%inspecci%')->orWhere('nombre', 'like', '%instalac%')->orWhere('nombre', 'like', '%mantenim%'))))),
                'entregas' => $query->where(fn ($q) => $q->whereHas('service', fn ($service) => $service->where('nombre', 'like', '%entrega%'))->orWhereIn('estado', [...ServiceOrder::ESTADOS_LISTOS, 'entregado'])),
                'inspecciones' => $query->whereHas('service', fn ($service) => $service->where('nombre', 'like', '%inspecci%')),
                'instalaciones' => $query->where(fn (Builder $orden) => $orden->whereHas('service', fn (Builder $service) => $service->where('nombre', 'like', '%instalac%')->orWhere('nombre', 'like', '%pozo%'))
                    ->orWhereHas('lines.service', fn (Builder $service) => $service->where('nombre', 'like', '%instalac%')->orWhere('nombre', 'like', '%pozo%'))),
                'mantenimientos' => $query->where(fn (Builder $orden) => $orden->whereHas('service', fn (Builder $service) => $service->where('nombre', 'like', '%mantenim%'))
                    ->orWhereHas('lines.service', fn (Builder $service) => $service->where('nombre', 'like', '%mantenim%'))),
                default => null,
            };
        }

        $orders = $query->paginate(15)->withQueryString()->through(function (ServiceOrder $order) {
            $servicios = mb_strtolower(collect([$order->service->nombre ?? ''])
                ->merge($order->lines->pluck('service.nombre'))->filter()->implode(' '));
            // Determinar acción sugerida en campo
            $accionSugerida = match (true) {
                in_array($order->estado, [...ServiceOrder::ESTADOS_LISTOS, 'entregado'], true) => 'entrega',
                str_contains($servicios, 'inspecci') => 'inspeccion',
                str_contains($servicios, 'mantenim') => 'mantenimiento',
                str_contains($servicios, 'instalac') || str_contains($servicios, 'pozo') => 'instalacion',
                $order->estado === 'pendiente_recepcion' => 'recojo',
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
                'tipo_servicio' => $order->resumenServicios(),
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
            'total_servicios' => (clone $base)
                ->count(),
            'pendientes' => (clone $base)
                ->whereIn('estado', ['pendiente_recepcion', 'recibido_planta', ...ServiceOrder::ESTADOS_LISTOS])
                ->count(),
            'en_proceso' => (clone $base)
                ->whereIn('estado', ['en_proceso', 'esperando_autorizacion', 'autorizado'])
                ->count(),
            'finalizados' => (clone $base)
                ->whereIn('estado', $finalizados)
                ->count(),
        ];

        return Inertia::render('tecnico-campo/dashboard', [
            'graficos' => $this->graficos($request, $fecha),
            'orders' => $orders,
            'kpis' => $kpis,
            'filters' => [
                'fecha' => $fecha,
                'hoy' => $hoy,
                'tab' => $statusTab,
                'tipo' => $tipoFiltro,
                'search' => $search,
            ],
        ]);
    }

    /** @return array<string, mixed> */
    private function graficos(Request $request, string $fecha): array
    {
        $ordenes = ServiceOrder::query()->accessibleToTechnician($request->user())
            ->where('estado', '!=', 'anulada');
        $pendientes = (clone $ordenes)->where(fn ($query) => $query->where('departamento_tecnico', 'campo')->orWhereNull('departamento_tecnico'))
            ->whereNotIn('estado', ServiceOrder::ESTADOS_CERRADOS_AL_TALLER)
            ->whereDate('fecha', '<=', $fecha);
        $prioridades = (clone $pendientes)->selectRaw('prioridad, COUNT(*) as total')->groupBy('prioridad')->pluck('total', 'prioridad');
        $inicio = Carbon::parse($fecha)->subDays(6)->startOfDay();
        $eventos = ServiceOrderEvent::query()->whereHas('serviceOrder', fn ($query) => $query->where('estado', '!=', 'anulada')
            ->when($request->user()->sede_id, fn ($sede) => $sede->whereIn('sede_id', Sede::idsAtendidosPor((int) $request->user()->sede_id))))
            ->where('user_id', $request->user()->id)
            ->whereBetween('created_at', [$inicio, $inicio->copy()->addDays(6)->endOfDay()]);
        $terminadas = (clone $eventos)->where('tipo', 'trabajo_completado')
            ->selectRaw('DATE(created_at) as dia, COUNT(DISTINCT service_order_id) as total')
            ->groupByRaw('DATE(created_at)')->pluck('total', 'dia');

        return [
            'prioridades' => collect(['urgente' => 'Urgente', 'alta' => 'Alta', 'normal' => 'Normal'])
                ->map(fn (string $nombre, string $clave) => ['nombre' => $nombre, 'valor' => (int) $prioridades->get($clave, 0)])->values(),
            'atrasadas' => [
                ['nombre' => 'En plazo', 'valor' => (clone $pendientes)->whereDate('fecha', '>=', $fecha)->count()],
                ['nombre' => 'Atrasadas', 'valor' => (clone $pendientes)->whereDate('fecha', '<', $fecha)->count()],
            ],
            'terminadas' => collect(range(0, 6))->map(function (int $dia) use ($inicio, $terminadas): array {
                $fechaDia = $inicio->copy()->addDays($dia);

                return ['nombre' => $fechaDia->format('d/m'), 'valor' => (int) $terminadas->get($fechaDia->toDateString(), 0)];
            }),
            'visitas' => collect([
                'recojo_campo' => 'Recojos', 'inspeccion_campo_finalizada' => 'Inspecciones',
                'instalacion_campo_realizada' => 'Instalaciones', 'mantenimiento_campo_finalizado' => 'Mantenimientos',
                'entrega_final_realizada' => 'Entregas',
            ])->map(fn (string $nombre, string $accion) => ['nombre' => $nombre, 'valor' => (clone $eventos)
                ->where(fn ($query) => $query->where('payload->accion', $accion)->orWhere('payload->eslabon_custodia', $accion))
                ->distinct()->count('service_order_id')])->values(),
        ];
    }
}
