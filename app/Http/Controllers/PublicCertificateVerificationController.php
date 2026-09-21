<?php

namespace App\Http\Controllers;

use App\Models\Certificate;
use Illuminate\Http\JsonResponse;

class PublicCertificateVerificationController extends Controller
{
    /**
     * Muestra la información pública de verificación de un certificado por su qr_token.
     */
    public function show(string $token): JsonResponse
    {
        $certificate = Certificate::query()
            ->with(['certificateType', 'client', 'certificateUnits'])
            ->where('qr_token', $token)
            ->firstOrFail();

        return response()->json([
            'numero' => $certificate->numero,
            'tipo' => $certificate->certificateType?->nombre,
            'cliente' => $certificate->client?->razon_social,
            'fecha_emision' => $certificate->fecha_emision?->toDateString(),
            'fecha_vigencia_hasta' => $certificate->fecha_vigencia_hasta?->toDateString(),
            'estado' => $certificate->estado,
            'unidades' => $certificate->certificateUnits->pluck('numero_serie_snapshot')->all(),
        ]);
    }
}
