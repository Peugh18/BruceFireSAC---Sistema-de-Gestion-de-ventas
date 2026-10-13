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
    public static function deLaVenta(Sale $sale, bool $incluirOrdenes = false): array
    {
        $certificados = Certificate::query()
            ->with('certificateType')
            ->withCount('certificateUnits')
            ->where('client_id', $sale->client_id)
            ->where(fn ($query) => $query
                ->where('sale_id', $sale->id)
                ->orWhereHas('ventasReutilizadas', fn ($venta) => $venta->where('sales.id', $sale->id))
                ->when($incluirOrdenes, fn ($query) => $query->orWhereHas('serviceOrder', fn ($order) => $order
                    ->where('sale_id', $sale->id)
                    ->where('client_id', $sale->client_id)
                    ->where('sede_id', $sale->sede_id))
                    ->orWhereHas('ordenesReutilizadas', fn ($order) => $order
                        ->where('sale_id', $sale->id)
                        ->where('client_id', $sale->client_id)
                        ->where('sede_id', $sale->sede_id))))
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
