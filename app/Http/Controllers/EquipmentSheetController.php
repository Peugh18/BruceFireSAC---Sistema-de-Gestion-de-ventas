<?php

namespace App\Http\Controllers;

use App\Models\Equipment;
use App\Models\ServiceOrder;
use App\Models\Team;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Ficha del extintor (BF-EQ): identificación, ubicación y la historia de las
 * órdenes que el usuario puede ver. Una orden ajena nunca aparece, y un
 * equipo sin ninguna orden visible se trata como inexistente.
 */
class EquipmentSheetController extends Controller
{
    public function show(Team $current_team, Request $request, Equipment $equipment): Response
    {
        $user = $request->user();

        $ordenes = $equipment->serviceOrders()
            ->with('service:id,nombre')
            ->where(fn ($query) => $user->hasAnyRole(['TecnicoCampo', 'TecnicoPlanta'])
                ? $query->accessibleToTechnician($user)
                : $query->visiblePara($user))
            ->latest('service_orders.id')
            ->get();

        abort_if($ordenes->isEmpty(), 404);

        $recibidos = DB::table('service_order_equipment')->where('equipment_id', $equipment->id)->pluck('recibido', 'service_order_id');
        $equipment->load('client:id,razon_social');

        return Inertia::render('equipos/show', [
            'equipment' => [
                'id' => $equipment->id,
                'numero_serie' => $equipment->numero_serie,
                'tipo_agente' => $equipment->tipo_agente,
                'capacidad' => $equipment->capacidad,
                'estado' => $equipment->estado,
                'ubicacion_actual' => $equipment->ubicacion_actual,
                'proxima_fecha_atencion' => $equipment->proxima_fecha_atencion?->toDateString(),
                'proxima_prueba_hidrostatica' => $equipment->proxima_prueba_hidrostatica?->toDateString(),
                'cliente' => $equipment->client->razon_social,
            ],
            'historia' => $ordenes->map(fn (ServiceOrder $orden) => [
                'id' => $orden->id,
                'codigo' => $orden->codigo,
                'estado' => $orden->estado,
                'servicio' => $orden->service?->nombre,
                'fecha' => $orden->created_at?->format('d/m/Y'),
                'recibido' => (bool) $recibidos->get($orden->id),
            ])->values(),
        ]);
    }
}
