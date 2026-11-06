<?php

namespace App\Http\Controllers;

use App\Models\Capacidad;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Lista de capacidades de extintor, igual que marcas y agentes: se agrega,
 * corrige o quita desde un modal. Quitar solo la esconde.
 */
class CapacidadController extends Controller
{
    public function index(): JsonResponse
    {
        $capacidades = Capacidad::query()->get(['id', 'nombre', 'activo']);
        $orden = array_flip(Capacidad::ordenar($capacidades->pluck('nombre')->all()));

        return response()->json(['capacidades' => $capacidades->sortBy(fn (Capacidad $c) => $orden[$c->nombre])->values()]);
    }

    public function store(Request $request): JsonResponse
    {
        $nombre = $this->validar($request);
        $existente = Capacidad::query()->where('nombre', $nombre)->first();

        if ($existente?->activo) {
            throw ValidationException::withMessages(['nombre' => "La capacidad {$nombre} ya está en la lista."]);
        }

        // Volver a agregar una que se quitó la reactiva.
        $capacidad = $existente ?? new Capacidad;
        $capacidad->fill(['nombre' => $nombre, 'activo' => true])->save();

        return response()->json(['capacidad' => $capacidad], 201);
    }

    public function update(Request $request, Capacidad $capacidad): JsonResponse
    {
        $nombre = $this->validar($request);
        if (Capacidad::query()->where('nombre', $nombre)->whereKeyNot($capacidad->id)->exists()) {
            throw ValidationException::withMessages(['nombre' => "La capacidad {$nombre} ya está en la lista."]);
        }
        $capacidad->update(['nombre' => $nombre]);

        return response()->json(['capacidad' => $capacidad]);
    }

    public function destroy(Capacidad $capacidad): JsonResponse
    {
        $capacidad->update(['activo' => false]);

        return response()->json(['ok' => true]);
    }

    protected function validar(Request $request): string
    {
        $request->validate(['nombre' => ['required', 'string', 'max:20']], ['nombre.required' => 'Escribe la capacidad, por ejemplo 30 lb.']);

        return Capacidad::normalizar((string) $request->input('nombre'))
            ?? throw ValidationException::withMessages(['nombre' => 'Escribe número y unidad: 30 lb, 50 kg o 9 L.']);
    }
}
