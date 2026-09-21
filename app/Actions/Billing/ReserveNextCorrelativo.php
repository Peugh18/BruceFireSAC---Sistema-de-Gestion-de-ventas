<?php

namespace App\Actions\Billing;

use App\Models\DocumentSeries;
use Illuminate\Support\Facades\DB;

class ReserveNextCorrelativo
{
    public function handle(string $tipoComprobante, string $serie): int
    {
        return DB::transaction(function () use ($tipoComprobante, $serie) {
            $documentSeries = DocumentSeries::query()
                ->where('tipo_comprobante', $tipoComprobante)
                ->where('serie', $serie)
                ->lockForUpdate()
                ->first();

            if (! $documentSeries) {
                $documentSeries = DocumentSeries::create([
                    'tipo_comprobante' => $tipoComprobante,
                    'serie' => $serie,
                    'correlativo_actual' => 0,
                ]);
            }

            $documentSeries->increment('correlativo_actual');

            return $documentSeries->refresh()->correlativo_actual;
        });
    }
}
