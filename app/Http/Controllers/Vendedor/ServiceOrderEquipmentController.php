<?php

namespace App\Http\Controllers\Vendedor;

use App\Actions\Equipment\QuickRegisterEquipment;
use App\Enums\EquipmentType;
use App\Http\Controllers\Controller;
use App\Models\Equipment;
use App\Models\ServiceOrder;
use App\Models\Team;
use App\Services\ServiceOrders\CasillerosDeLaOrden;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ServiceOrderEquipmentController extends Controller
{
    public function store(Team $current_team, ServiceOrder $service_order, Request $request, QuickRegisterEquipment $action): RedirectResponse
    {
        $this->asegurarAcceso($service_order, $request);
        $data = $request->validate([
            'numero_serie' => ['nullable', 'string', 'max:100'],
            'service_order_line_id' => ['nullable', 'integer', 'exists:service_order_lines,id'],
            'service_id' => ['nullable', 'integer', 'exists:services,id'],
            'adicional' => ['nullable', 'boolean'],
            'tipo_agente' => ['nullable', Rule::in(EquipmentType::etiquetas())],
            'capacidad' => ['nullable', 'string', 'max:30'],
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
        $this->asegurarAcceso($service_order, $request);

        $data = $request->validate([
            'equipos' => ['required', 'array'],
            'equipos.*' => ['integer'],
        ]);

        $ids = array_values(array_map('intval', $data['equipos']));
        DB::transaction(function () use ($service_order, $ids): void {
            $service_order = ServiceOrder::query()->lockForUpdate()->findOrFail($service_order->id);
            $this->asegurarEditable($service_order);
            $attached = $service_order->equipments()->pluck('equipment.id')->all();
            $sortedIds = $ids;
            sort($sortedIds);
            sort($attached);
            abort_if($sortedIds !== $attached, 422);

            foreach ($ids as $index => $equipmentId) {
                DB::table('service_order_equipment')
                    ->where('service_order_id', $service_order->id)
                    ->where('equipment_id', $equipmentId)
                    ->update(['posicion' => $index + 1]);
            }
        });

        return back()->with('success', 'Orden de extintores actualizado.');
    }

    /**
     * Saca un extintor de la orden (se escaneó por error). Solo mientras la
     * orden se recibe o se trabaja y si aún no tiene checklist en ella.
     */
    public function destroy(Team $current_team, ServiceOrder $service_order, Equipment $equipment, Request $request): RedirectResponse
    {
        $this->asegurarAcceso($service_order, $request);
        DB::transaction(function () use ($service_order, $equipment, $request): void {
            $service_order = ServiceOrder::query()->lockForUpdate()->findOrFail($service_order->id);
            $this->asegurarEditable($service_order);
            $pivot = $service_order->equipments()->whereKey($equipment->id)->first()?->pivot;
            abort_if($pivot === null, 404);

            if ($service_order->checklists()->where('equipment_id', $equipment->id)->exists()) {
                throw ValidationException::withMessages(['equipo' => 'Ese extintor ya se revisó en el taller: no se puede quitar.']);
            }

            $service_order->equipments()->detach($equipment->id);
            $linea = $service_order->lines()->whereKey($pivot->getAttribute('service_order_line_id'))->first();
            if ($linea?->adicional && ! $service_order->equipments()->wherePivot('service_order_line_id', $linea->id)->exists()) {
                $linea->delete();
            }
            $service_order->events()->create(['tipo' => 'otro', 'user_id' => $request->user()->id, 'payload' => ['accion' => 'extintor_quitado', 'equipment_id' => $equipment->id, 'numero_serie' => $equipment->numero_serie]]);
        });

        return back()->with('success', "Extintor {$equipment->numero_serie} quitado de la orden.");
    }

    private function asegurarAcceso(ServiceOrder $orden, Request $request): void
    {
        if ($request->routeIs('tecnico-planta.*')) {
            abort_unless($orden->atendiblePor($request->user()), 404);

            return;
        }

        abort_unless(ServiceOrder::query()->visiblePara($request->user())->whereKey($orden->id)->exists(), 404);
    }

    private function asegurarEditable(ServiceOrder $orden): void
    {
        if (! in_array($orden->estado, CasillerosDeLaOrden::ESTADOS_EDITABLES, true)) {
            throw ValidationException::withMessages(['equipo' => 'Esta orden ya no admite cambios de extintores.']);
        }
    }
}
