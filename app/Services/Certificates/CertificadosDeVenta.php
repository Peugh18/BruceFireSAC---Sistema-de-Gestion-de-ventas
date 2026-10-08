<?php

namespace App\Services\Certificates;

use App\Models\Certificate;
use App\Models\Sale;

/**
 * Certificados emitidos de una venta, con su tipo y número de unidades
 * (antes vivía como método estático de SaleCertificateController y lo usaba
 * también SaleController).
 */
class CertificadosDeVenta
{
    /**
     * @return list<array<string, mixed>>
     */
    public static function deLaVenta(Sale $sale): array
    {
        $certificados = Certificate::query()
            ->with('certificateType')
            ->withCount('certificateUnits')
            ->where('sale_id', $sale->id)
            ->orderBy('id')
            ->get();

        $listado = [];
        foreach ($certificados as $certificate) {
            $listado[] = [
                'id' => $certificate->id,
                'numero' => $certificate->numero,
                'tipo' => $certificate->certificateType->nombre,
                'tipo_codigo' => $certificate->certificateType->codigo,
                'revision' => $certificate->revision,
                'estado' => $certificate->estado,
                'referencia' => $certificate->referencia,
                'unidades' => $certificate->certificate_units_count,
            ];
        }

        return $listado;
    }
}
