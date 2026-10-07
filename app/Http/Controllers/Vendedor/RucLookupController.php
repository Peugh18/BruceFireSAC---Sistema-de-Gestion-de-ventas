<?php

namespace App\Http\Controllers\Vendedor;

use App\Http\Controllers\Controller;
use App\Services\Sunat\RucLookupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class RucLookupController extends Controller
{
    public function show(Request $request, RucLookupService $lookupService): JsonResponse
    {
        $validated = $request->validate([
            'numero_documento' => ['required', 'string', 'regex:/^(\d{8}|\d{11})$/'],
        ]);

        try {
            return response()->json($lookupService->lookup($validated['numero_documento']));
        } catch (Throwable $exception) {
            // El mensaje del error puede traer la URL de la API con el token de
            // APIsPeru: al navegador solo un mensaje genérico, y al log sin
            // credenciales.
            Log::warning('No se pudo consultar el documento en APIsPeru.', [
                'numero_documento' => $validated['numero_documento'],
                'error' => $this->sinCredenciales($exception->getMessage()),
            ]);

            return response()->json([
                'message' => 'No se pudo consultar el documento en este momento. Intenta de nuevo en unos minutos.',
            ], 422);
        }
    }

    /**
     * Quita el token de APIsPeru del mensaje antes de que se registre o se
     * muestre.
     */
    protected function sinCredenciales(string $mensaje): string
    {
        $token = (string) config('services.apisperu.token');

        if ($token !== '') {
            $mensaje = str_replace($token, '[OCULTO]', $mensaje);
        }

        return (string) preg_replace('/token=[^&\s"\']+/i', 'token=[OCULTO]', $mensaje);
    }
}
