<?php

namespace App\Http\Controllers;

use App\Enums\EquipmentType;
use App\Models\Agente;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

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
        $agente = DB::transaction(function () use ($datos): Agente {
            $agentes = Agente::query()->lockForUpdate()->get();
            $existente = $agentes->first(fn (Agente $agente): bool => $agente->coincideCon($datos['nombre']));

            if ($existente) {
                if ($existente->activo || Str::lower(Str::ascii($existente->nombre)) !== Str::lower(Str::ascii($datos['nombre']))) {
                    throw ValidationException::withMessages(['nombre' => "Ese nombre ya pertenece al agente {$existente->nombre}."]);
                }
                if ($existente->tipo_base->value !== $datos['tipo_base']) {
                    throw ValidationException::withMessages(['tipo_base' => 'Para reactivarlo elige su tipo original: '.$existente->tipo_base->etiqueta().'.']);
                }
                $existente->update(['activo' => true]);

                return $existente;
            }

            return Agente::create([...$datos, 'orden' => (int) $agentes->max('orden') + 1]);
        });

        return response()->json(['agente' => $agente], 201);
    }

    public function update(Request $request, Agente $agente): JsonResponse
    {
        $datos = $this->validar($request, $agente);
        $agente = DB::transaction(function () use ($datos, $agente): Agente {
            $agentes = Agente::query()->lockForUpdate()->get();
            $actual = $agentes->firstWhere('id', $agente->id);
            abort_if($actual === null, 404);

            if ($agentes->contains(fn (Agente $otro): bool => $otro->id !== $actual->id && $otro->coincideCon($datos['nombre']))) {
                throw ValidationException::withMessages(['nombre' => 'Ese nombre ya pertenece a otro agente, aunque se haya renombrado o quitado.']);
            }
            if ($actual->tipo_base->value !== $datos['tipo_base']) {
                throw ValidationException::withMessages(['tipo_base' => 'Renombrar conserva el tipo original. Para otro tipo, agrega un agente distinto.']);
            }

            $anteriores = $actual->nombres_anteriores ?? [];
            if (mb_strtolower($actual->nombre) !== mb_strtolower($datos['nombre'])) {
                $anteriores[] = $actual->nombre;
            }
            $actual->update(['nombre' => $datos['nombre'], 'nombres_anteriores' => array_values(array_unique($anteriores))]);

            return $actual;
        });

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
