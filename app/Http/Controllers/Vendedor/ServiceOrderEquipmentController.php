<?php

namespace App\Http\Controllers\Vendedor;

use App\Actions\Equipment\QuickRegisterEquipment;
use App\Http\Controllers\Controller;
use App\Models\ServiceOrder;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ServiceOrderEquipmentController extends Controller
{
    public function store(Team $current_team, ServiceOrder $service_order, Request $request, QuickRegisterEquipment $action): RedirectResponse
    {
        $sedeId = $request->user()->sedeRestringidaId();
        abort_if($sedeId !== null && (int) $service_order->sede_id !== $sedeId, 404);
        $data = $request->validate([
            'numero_serie' => ['nullable', 'string', 'max:100'],
            'service_order_line_id' => ['nullable', 'integer', 'exists:service_order_lines,id'],
            'service_id' => ['nullable', 'integer', 'exists:services,id'],
            'adicional' => ['nullable', 'boolean'],
            'marca' => ['nullable', 'string', 'max:100'],
            'serie_fabricante' => ['nullable', 'string', 'max:100'],
            'anio_fabricacion' => ['nullable', 'string', 'max:100'],
            'observaciones_recepcion' => ['nullable', 'string', 'max:1000'],
        ]);
        $action->execute($service_order, [...$data, 'recibido' => false], $request->user());

        return back()->with('success', 'Extintor agregado a la orden.');
    }

    public function reorder(Team $current_team, ServiceOrder $service_order, Request $request): RedirectResponse
    {
        $sedeId = $request->user()->sedeRestringidaId();
        abort_if($sedeId !== null && (int) $service_order->sede_id !== $sedeId, 404);

        $data = $request->validate([
            'equipos' => ['required', 'array'],
            'equipos.*' => ['integer'],
        ]);

        $ids = array_values(array_map('intval', $data['equipos']));
        $attached = $service_order->equipments()->pluck('equipment.id')->all();
        $sortedIds = $ids;
        $sortedAttached = $attached;
        sort($sortedIds);
        sort($sortedAttached);
        abort_if($sortedIds !== $sortedAttached, 422);

        DB::transaction(function () use ($service_order, $ids): void {
            foreach ($ids as $index => $equipmentId) {
                DB::table('service_order_equipment')
                    ->where('service_order_id', $service_order->id)
                    ->where('equipment_id', $equipmentId)
                    ->update(['posicion' => $index + 1]);
            }
        });

        return back()->with('success', 'Orden de extintores actualizado.');
    }
}
