<?php

namespace App\Http\Controllers\Vendedor;

use App\Actions\Equipment\QuickRegisterEquipment;
use App\Http\Controllers\Controller;
use App\Models\ServiceOrder;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ServiceOrderEquipmentController extends Controller
{
    public function store(Team $current_team, ServiceOrder $service_order, Request $request, QuickRegisterEquipment $action): RedirectResponse
    {
        $sedeId = $request->user()->sedeRestringidaId();
        abort_if($sedeId !== null && (int) $service_order->sede_id !== $sedeId, 404);
        $data = $request->validate(['numero_serie' => ['nullable', 'string', 'max:100'], 'tipo_agente' => ['required_without:numero_serie', 'nullable', 'string', 'max:100'], 'capacidad' => ['required_without:numero_serie', 'nullable', 'string', 'max:100'], 'marca' => ['nullable', 'string', 'max:100'], 'serie_fabricante' => ['nullable', 'string', 'max:100'], 'observaciones_recepcion' => ['nullable', 'string', 'max:1000']]);
        $action->execute($service_order, [...$data, 'recibido' => false], $request->user());

        return back()->with('success', 'Extintor agregado a la orden.');
    }
}
