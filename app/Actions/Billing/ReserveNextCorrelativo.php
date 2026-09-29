<?php

namespace App\Actions\Billing;

use App\Models\DocumentSeries;
use Illuminate\Support\Facades\DB;

class ReserveNextCorrelativo
{
    /**
     * Reutiliza primero el menor número liberado (de un comprobante que se
     * corrigió antes de llegar a SUNAT) para no dejar huecos en la serie.
     */
    public function handle(string $tipoComprobante, string $serie): int
    {
        return DB::transaction(function () use ($tipoComprobante, $serie) {
            $documentSeries = $this->lockSerie($tipoComprobante, $serie);

            $liberado = $documentSeries->correlativosLiberados()->orderBy('correlativo')->lockForUpdate()->first();

            if ($liberado) {
                $liberado->delete();

                return $liberado->correlativo;
            }

            $documentSeries->increment('correlativo_actual');

            return $documentSeries->refresh()->correlativo_actual;
        });
    }

    /**
     * Devuelve a la serie el número de un comprobante que nunca se envió a
     * SUNAT. Si era el último emitido, simplemente retrocede el contador.
     */
    public function liberar(string $tipoComprobante, string $serie, int $correlativo): void
    {
        DB::transaction(function () use ($tipoComprobante, $serie, $correlativo) {
            $documentSeries = $this->lockSerie($tipoComprobante, $serie);

            if ($correlativo === $documentSeries->correlativo_actual) {
                $documentSeries->decrement('correlativo_actual');

                return;
            }

            $documentSeries->correlativosLiberados()->firstOrCreate(['correlativo' => $correlativo]);
        });
    }

    protected function lockSerie(string $tipoComprobante, string $serie): DocumentSeries
    {
        $documentSeries = DocumentSeries::query()
            ->where('tipo_comprobante', $tipoComprobante)
            ->where('serie', $serie)
            ->lockForUpdate()
            ->first();

        return $documentSeries ?? DocumentSeries::create([
            'tipo_comprobante' => $tipoComprobante,
            'serie' => $serie,
            'correlativo_actual' => 0,
        ]);
    }
}
