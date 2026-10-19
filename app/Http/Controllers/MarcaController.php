<?php

namespace App\Http\Controllers;

use App\Models\Marca;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Lista de marcas de extintor, igual que la de agentes: se agrega, renombra o
 * quita desde un modal pequeño. Quitar solo la esconde; los extintores ya
 * registrados conservan su marca.
 */
class MarcaController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['marcas' => Marca::query()->orderBy('nombre')->get(['id', 'nombre', 'activo'])]);
    }

    public function store(Request $request): JsonResponse
    {
        $nombre = $this->validar($request);
        $existente = Marca::query()->whereRaw('LOWER(nombre) = ?', [mb_strtolower($nombre)])->first();

        if ($existente?->activo) {
            throw ValidationException::withMessages(['nombre' => "La marca {$existente->nombre} ya está en la lista."]);
        }

        // Volver a agregar una que se quitó la reactiva.
        $marca = $existente ?? new Marca;
        $marca->fill(['nombre' => $existente->nombre ?? $nombre, 'activo' => true])->save();

        return response()->json(['marca' => $marca], 201);
    }

    public function update(Request $request, Marca $marca): JsonResponse
    {
        $marca->update(['nombre' => $this->validar($request, $marca)]);

        return response()->json(['marca' => $marca]);
    }

    public function destroy(Marca $marca): JsonResponse
    {
        $marca->update(['activo' => false]);

        return response()->json(['ok' => true]);
    }

    protected function validar(Request $request, ?Marca $marca = null): string
    {
        $request->merge(['nombre' => preg_replace('/\s+/', ' ', trim((string) $request->input('nombre')))]);

        return $request->validate([
            'nombre' => ['required', 'string', 'min:2', 'max:60', ...($marca ? [Rule::unique('marcas', 'nombre')->ignore($marca)] : [])],
        ], [
            'nombre.required' => 'Escribe el nombre de la marca.',
            'nombre.unique' => 'Ya hay una marca con ese nombre.',
        ])['nombre'];
    }
}
