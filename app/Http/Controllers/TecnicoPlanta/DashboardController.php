<?php

namespace App\Http\Controllers\TecnicoPlanta;

use App\Http\Controllers\Controller;
use App\Models\Sede;
use App\Models\ServiceOrder;
use App\Models\ServiceOrderEvent;
use App\Models\Team;
use App\Services\Tecnico\ConversacionDeLaOrden;
use App\Services\Tecnico\PasoDelTaller;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Dashboard operativo del Técnico de Planta (§5.4, §85.4).
     * Visualización mobile-first de colas por estado, KPIs táctiles y órdenes de taller.
     */
    public function index(Request $request, Team $current_team, ConversacionDeLaOrden $conversacion): Response
    {
        $request->validate(['fecha' => ['nullable', 'date_format:Y-m-d']]);
        $hoy = now('America/Lima')->toDateString();
        $fecha = $request->input('fecha') ?: $hoy;
        $tab = $request->string('tab', 'todas')->toString();
        $search = trim((string) $request->input('search', ''));

        // Query base para órdenes de Planta
        $query = ServiceOrder::query()
            ->accessibleToTechnician($request->user())
            ->where(function ($q) {
                $q->where('departamento_tecnico', 'planta')
                    ->orWhereNull('departamento_tecnico');
            })
            ->whereNotIn('estado', ServiceOrder::ESTADOS_CERRADOS_AL_TALLER)
            ->with([
                'client:id,nombre_comercial,razon_social,telefono,numero_documento',
                'service:id,nombre',
                'sede:id,nombre,ubigeo', 'sede.ubicacion',
                'deficiencies:id,service_order_id,componente,estado,requiere_autorizacion',
                'events' => fn ($q) => $q->latest('id')->limit(1),
                'equipments:equipment.id', 'checklists:id,service_order_id,equipment_id',
            ])
            ->whereDate('fecha', $fecha === $hoy ? '<=' : '=', $fecha)
            ->orderByRaw("CASE prioridad WHEN 'urgente' THEN 0 WHEN 'alta' THEN 1 ELSE 2 END")
            ->orderBy('fecha')
            ->orderBy('id');

        $cola = clone $query;

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
            'atrasadas' => $query->whereDate('fecha', '<', $hoy),
            default => null,
        };

        $orders = $query->paginate(15)->withQueryString();
        $noLeidos = $conversacion->noLeidos($request->user(), $orders->getCollection()->pluck('id'));

        $orders->through(function (ServiceOrder $order) use ($noLeidos) {
            $deficienciesCount = $order->deficiencies->count();
            $pendingAuthCount = $order->deficiencies->where('requiere_autorizacion', true)->where('estado', 'pendiente')->count();

            return [
                'id' => $order->id,
                'codigo' => $order->codigo,
                'cliente' => $order->client->nombre_comercial ?: $order->client->razon_social,
                'cliente_doc' => $order->client->numero_documento,
                'telefono' => $order->client->telefono,
                'sede' => $order->sede?->nombre,
                'tipo_servicio' => $order->resumenServicios(),
                'fecha' => $order->fecha->toDateString(),
                'prioridad' => $order->prioridad,
                'estado' => $order->estado,
                'estado_coarse' => $order->coarseLabel(),
                'observaciones' => $order->observaciones,
                'deficiencias_count' => $deficienciesCount,
                'requiere_autorizacion_count' => $pendingAuthCount,
                'ultimo_evento' => $order->events->first()?->tipo,
                'paso' => PasoDelTaller::de($order),
                'no_leidos' => $noLeidos[$order->id] ?? 0,
            ];
        });

        // KPIs operativos de Planta (§5.4)
        $kpis = [
            'pendientes_recepcion' => (clone $cola)
                ->where('estado', 'pendiente_recepcion')
                ->count(),
            'en_taller' => (clone $cola)
                ->whereIn('estado', ['recibido_planta', 'en_proceso', 'autorizado'])
                ->count(),
            'esperando_autorizacion' => (clone $cola)
                ->where('estado', 'esperando_autorizacion')
                ->count(),
            'atrasadas' => (clone $cola)
                ->whereDate('fecha', '<', $hoy)
                ->count(),
        ];

        return Inertia::render('tecnico-planta/dashboard', [
            'graficos' => $this->graficos($request, $fecha),
            'orders' => $orders,
            'kpis' => $kpis,
            'filters' => [
                'tab' => $tab,
                'search' => $search,
                'fecha' => $fecha,
                'hoy' => $hoy,
            ],
        ]);
    }

    /** @return array<string, mixed> */
    private function graficos(Request $request, string $fecha): array
    {
        $ordenes = ServiceOrder::query()->accessibleToTechnician($request->user())
            ->where('estado', '!=', 'anulada');
        $pendientes = (clone $ordenes)->where(fn ($query) => $query->where('departamento_tecnico', 'planta')->orWhereNull('departamento_tecnico'))
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

        ];
    }
}
