<?php

namespace App\Http\Controllers;

use App\Models\ChispaRegistro;
use App\Models\Product;
use App\Models\Service;
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

    /**
     * La gerencia confirma el cambio de precio que Chispa dejó preparado.
     * Solo se aplica lo guardado en la sesión: el navegador no elige el precio.
     */
    public function confirmarPrecio(Request $request): JsonResponse
    {
        $borrador = $request->session()->pull('chispa.precio');
        abort_unless(is_array($borrador), 422, 'No hay un cambio de precio por confirmar.');

        $modelo = $borrador['tipo'] === 'producto'
            ? Product::query()->whereKey((int) $borrador['id'])->firstOrFail()
            : Service::query()->whereKey((int) $borrador['id'])->firstOrFail();
        $antes = (float) $modelo->precio_venta;
        $modelo->update(['precio_venta' => $borrador['precio']]);

        return response()->json(['mensaje' => "Listo: {$modelo->nombre} pasó de S/ ".number_format($antes, 2).' a S/ '.number_format((float) $borrador['precio'], 2).'.']);
    }

    /** El usuario marca si la respuesta le sirvió (solo la suya). */
    public function valorar(Request $request, ChispaRegistro $registro): JsonResponse
    {
        abort_unless($registro->user_id === $request->user()->id, 404);

        $registro->update($request->validate(['util' => ['required', 'boolean']]));

        return response()->json(['ok' => true]);
    }
}
