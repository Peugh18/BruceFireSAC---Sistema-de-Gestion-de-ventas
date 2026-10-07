<?php

namespace App\Http\Controllers;

use App\Models\ServiceOrder;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TechnicalOrderAssignmentController extends Controller
{
    public function take(Team $current_team, ServiceOrder $service_order, Request $request): RedirectResponse
    {
        return DB::transaction(function () use ($service_order, $request): RedirectResponse {
            // Bloqueada y releída: dos técnicos que toman la orden a la vez no
            // pueden pasar los dos la comprobación y que el segundo pise al
            // primero.
            $orden = ServiceOrder::query()->lockForUpdate()->findOrFail($service_order->id);

            abort_if($orden->tecnico_id !== null && $orden->tecnico_id !== $request->user()->id, 404);

            // Una orden sin área asignada la puede tomar cualquier técnico (las
            // dos pantallas la muestran).
            $roles = match ($orden->departamento_tecnico) {
                'campo' => ['TecnicoCampo'],
                'planta' => ['TecnicoPlanta'],
                default => ['TecnicoCampo', 'TecnicoPlanta'],
            };
            abort_unless($request->user()->hasAnyRole($roles), 403);

            $orden->update(['tecnico_id' => $request->user()->id]);
            $orden->events()->create(['tipo' => 'otro', 'user_id' => $request->user()->id, 'payload' => ['accion' => 'orden_tomada', 'mensaje' => "Orden tomada por {$request->user()->name}."]]);

            return back()->with('success', 'La orden quedó a tu cargo.');
        });
    }
}
