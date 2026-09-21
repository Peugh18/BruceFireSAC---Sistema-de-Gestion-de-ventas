<?php

namespace App\Http\Controllers\Vendedor;

use App\Http\Controllers\Controller;
use App\Models\ServiceOrder;
use App\Models\Team;
use Inertia\Inertia;
use Inertia\Response;

class CommunicationController extends Controller
{
    public function index(Team $current_team): Response
    {
        $orders = ServiceOrder::query()
            ->with(['client', 'events' => fn ($query) => $query->latest('created_at')->limit(1)])
            ->where(fn ($query) => $query
                ->whereNull('departamento_tecnico')
                ->orWhere('departamento_tecnico', 'planta'))
            ->whereHas('events')
            ->orderByDesc('updated_at')
            ->paginate(15)
            ->through(fn (ServiceOrder $order) => [
                'id' => $order->id,
                'codigo' => $order->codigo,
                'cliente' => $order->client->razon_social,
                'estado' => $order->estado,
                'ultimo_evento' => $order->events->first(),
            ]);

        return Inertia::render('vendedor/comunicacion/index', [
            'orders' => $orders,
        ]);
    }
}
