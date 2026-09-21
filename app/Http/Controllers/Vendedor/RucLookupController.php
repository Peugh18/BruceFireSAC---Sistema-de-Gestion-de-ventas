<?php

namespace App\Http\Controllers\Vendedor;

use App\Http\Controllers\Controller;
use App\Services\Sunat\RucLookupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }
    }
}
