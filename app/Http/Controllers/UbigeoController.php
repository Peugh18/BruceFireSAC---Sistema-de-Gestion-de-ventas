<?php

namespace App\Http\Controllers;

use App\Models\Ubigeo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Buscador del catálogo de ubigeos para los formularios de dirección.
 */
class UbigeoController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $search = $request->string('search')->toString();

        if (trim($search) === '') {
            return response()->json([]);
        }

        return response()->json(
            Ubigeo::query()->buscar($search)->orderBy('departamento')->orderBy('provincia')->orderBy('distrito')->limit(20)->get()
                ->map(fn (Ubigeo $ubigeo) => $ubigeo->paraFormulario()),
        );
    }
}
