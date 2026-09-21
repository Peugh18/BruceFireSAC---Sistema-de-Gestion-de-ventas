<?php

namespace App\Http\Controllers\Vendedor;

use App\Http\Controllers\Controller;
use App\Models\InventoryUnit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InventoryLookupController extends Controller
{
    /**
     * Resuelve un número de serie escaneado a su InventoryUnit exacta.
     * Usado por el escáner de Nueva Venta (Fase 4) — el Vendedor nunca
     * elige unidades al azar, siempre escanea la unidad física exacta.
     */
    public function bySerial(Request $request): JsonResponse
    {
        $data = $request->validate([
            'numero_serie' => ['required', 'string'],
            'sede_almacen_id' => ['required', 'integer', 'exists:sedes,id'],
        ]);

        $unit = InventoryUnit::with('catalogItem')
            ->where('numero_serie', $data['numero_serie'])
            ->first();

        if (! $unit) {
            return response()->json(['message' => 'No se encontró ninguna unidad con esa serie.'], 404);
        }

        if ($unit->sede_almacen_id !== (int) $data['sede_almacen_id']) {
            return response()->json(['message' => 'Esa unidad no pertenece al almacén de la sede activa.'], 422);
        }

        if (! $unit->estaDisponible()) {
            return response()->json(['message' => "Esa unidad ya no está disponible (estado: {$unit->estado})."], 422);
        }

        return response()->json([
            'inventory_unit_id' => $unit->id,
            'numero_serie' => $unit->numero_serie,
            'catalog_item_id' => $unit->catalog_item_id,
            'nombre' => $unit->catalogItem->nombre,
            'precio_venta' => $unit->catalogItem->precio_venta,
        ]);
    }
}
