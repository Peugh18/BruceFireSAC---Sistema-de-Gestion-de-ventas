<?php

namespace App\Http\Controllers\Gerente;

use App\Http\Controllers\Controller;
use App\Models\SunatCodigoProducto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Busca en el catálogo 25 de SUNAT para elegir el código de un producto o de
 * un tipo de servicio sin escribir los 8 dígitos a mano.
 */
class SunatCodigoProductoController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $buscar = trim((string) $request->query('buscar', ''));
        $tipo = in_array($request->query('tipo'), ['producto', 'servicio'], true) ? (string) $request->query('tipo') : null;

        return response()->json([
            'codigos' => mb_strlen($buscar) < 2 ? [] : SunatCodigoProducto::buscar($buscar, tipo: $tipo)->map->only(['codigo', 'descripcion', 'clase'])->values(),
        ]);
    }
}
