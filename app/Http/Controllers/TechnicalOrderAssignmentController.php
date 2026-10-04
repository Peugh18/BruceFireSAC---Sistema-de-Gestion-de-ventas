<?php

namespace App\Http\Controllers;

use App\Models\ServiceOrder;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TechnicalOrderAssignmentController extends Controller
{
    public function take(Team $current_team, ServiceOrder $service_order, Request $request): RedirectResponse
    {
        abort_if($service_order->tecnico_id !== null && $service_order->tecnico_id !== $request->user()->id, 404);

        // Una orden sin área asignada la puede tomar cualquier técnico (las
        // dos pantallas la muestran).
        $roles = match ($service_order->departamento_tecnico) {
            'campo' => ['TecnicoCampo'],
            'planta' => ['TecnicoPlanta'],
            default => ['TecnicoCampo', 'TecnicoPlanta'],
        };
        abort_unless($request->user()->hasAnyRole($roles), 403);

        $service_order->update(['tecnico_id' => $request->user()->id]);
        $service_order->events()->create(['tipo' => 'otro', 'user_id' => $request->user()->id, 'payload' => ['accion' => 'orden_tomada', 'mensaje' => "Orden tomada por {$request->user()->name}."]]);

        return back()->with('success', 'La orden quedó a tu cargo.');
    }
}
