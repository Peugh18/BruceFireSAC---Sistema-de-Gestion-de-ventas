<?php

namespace App\Http\Controllers;

use App\Enums\EquipmentType;
use App\Models\Agente;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Lista de agentes extintores que se eligen en los formularios. Se agrega,
 * renombra o quita desde un modal pequeño, sin pantalla aparte. Quitar solo
 * lo esconde de las listas: los extintores ya registrados conservan su nombre.
 */
class AgenteController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'agentes' => Agente::query()->orderBy('orden')->orderBy('nombre')->get(['id', 'nombre', 'tipo_base', 'activo']),
            'tipos' => EquipmentType::opciones(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $datos = $this->validar($request);
        $agente = Agente::query()->whereRaw('LOWER(nombre) = ?', [mb_strtolower($datos['nombre'])])->first();

        // Volver a agregar uno que se había quitado lo reactiva.
        if ($agente) {
            $agente->update([...$datos, 'activo' => true]);
        } else {
            $agente = Agente::create([...$datos, 'orden' => (int) Agente::max('orden') + 1]);
        }

        return response()->json(['agente' => $agente], 201);
    }

    public function update(Request $request, Agente $agente): JsonResponse
    {
        $agente->update($this->validar($request, $agente));

        return response()->json(['agente' => $agente]);
    }

    public function destroy(Agente $agente): JsonResponse
    {
        abort_if(Agente::query()->where('activo', true)->count() <= 1, 422, 'Debe quedar al menos un agente.');
        $agente->update(['activo' => false]);

        return response()->json(['ok' => true]);
    }

    /** @return array{nombre: string, tipo_base: string} */
    protected function validar(Request $request, ?Agente $agente = null): array
    {
        $request->merge(['nombre' => preg_replace('/\s+/', ' ', trim((string) $request->input('nombre')))]);

        return $request->validate([
            'nombre' => ['required', 'string', 'min:2', 'max:60', $agente ? Rule::unique('agentes', 'nombre')->ignore($agente) : 'string'],
            'tipo_base' => ['required', Rule::in(EquipmentType::values())],
        ], [
            'nombre.required' => 'Escribe el nombre del agente.',
            'nombre.unique' => 'Ya hay un agente con ese nombre.',
            'tipo_base.required' => 'Elige a qué tipo pertenece.',
        ]);
    }
}
