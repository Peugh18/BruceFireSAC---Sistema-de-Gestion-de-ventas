<?php

namespace App\Http\Controllers\Vendedor;

use App\Http\Controllers\Controller;
use App\Http\Requests\ServiceOrders\StoreServiceOrderRequest;
use App\Models\Client;
use App\Models\ServiceOrder;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ServiceOrderController extends Controller
{
    public function index(Team $current_team, Request $request): Response
    {
        $estado = $request->string('estado')->toString();
        $estadoMap = [
            'en_camino' => ['pendiente_recepcion', 'recibido_planta', 'en_revision', 'esperando_autorizacion'],
            'en_proceso' => ['autorizado', 'en_proceso', 'trabajo_terminado', 'pendiente_datos', 'datos_completos'],
            'completadas' => ['listo_certificado', 'listo_entrega', 'entregado', 'cerrado'],
        ];

        $orders = ServiceOrder::query()
            ->with(['client', 'tecnico'])
            ->when(isset($estadoMap[$estado]), fn ($query) => $query->whereIn('estado', $estadoMap[$estado]))
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (ServiceOrder $order) => [
                'id' => $order->id,
                'codigo' => $order->codigo,
                'cliente' => $order->client->razon_social,
                'tecnico' => $order->tecnico?->name,
                'tipo_servicio' => $order->tipo_servicio,
                'fecha' => $order->fecha->toDateString(),
                'estado' => $order->estado,
                'coarse_label' => $order->coarseLabel(),
                'departamento_tecnico' => $order->departamento_tecnico,
                'prioridad' => $order->prioridad,
            ]);

        return Inertia::render('vendedor/ordenes-servicio/index', [
            'orders' => $orders,
            'filters' => ['estado' => $estado],
            'clients' => Client::query()
                ->orderBy('razon_social')
                ->get(['id', 'razon_social', 'numero_documento']),
        ]);
    }

    public function store(Team $current_team, StoreServiceOrderRequest $request): RedirectResponse
    {
        $order = ServiceOrder::create([
            ...$request->validated(),
            'codigo' => 'OT-'.now()->year.'-'.str_pad((string) (ServiceOrder::count() + 1), 4, '0', STR_PAD_LEFT),
            'prioridad' => $request->input('prioridad', 'normal'),
            'estado' => 'pendiente_recepcion',
        ]);

        $order->events()->create([
            'tipo' => 'creada',
            'user_id' => $request->user()->id,
            'payload' => [
                'mensaje' => 'Orden de servicio creada por vendedor.',
            ],
        ]);

        return back();
    }

    public function show(Team $current_team, ServiceOrder $service_order): Response
    {
        $service_order->load([
            'client',
            'tecnico',
            'events' => fn ($query) => $query->with('user')->orderBy('created_at'),
        ]);

        return Inertia::render('vendedor/ordenes-servicio/show', [
            'serviceOrder' => $service_order,
        ]);
    }
}
