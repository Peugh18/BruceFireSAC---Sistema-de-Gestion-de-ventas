<?php

namespace App\Http\Controllers;

use App\Models\ChispaRegistro;
use App\Services\Chispa\ChispaAssistant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ChispaController extends Controller
{
    public function __invoke(Request $request, ChispaAssistant $chispa): JsonResponse
    {
        $datos = $request->validate([
            'mensajes' => ['required', 'array', 'min:1', 'max:12'],
            'mensajes.*.role' => ['required', Rule::in(['user', 'assistant'])],
            'mensajes.*.content' => ['required', 'string', 'max:1500'],
            'pagina' => ['nullable', 'string', 'max:200'],
        ]);

        return response()->json($chispa->responder($request->user(), $datos['mensajes'], $datos['pagina'] ?? null));
    }

    /** El usuario marca si la respuesta le sirvió (solo la suya). */
    public function valorar(Request $request, ChispaRegistro $registro): JsonResponse
    {
        abort_unless($registro->user_id === $request->user()->id, 404);

        $registro->update($request->validate(['util' => ['required', 'boolean']]));

        return response()->json(['ok' => true]);
    }
}
