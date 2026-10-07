<?php

namespace App\Services;

use App\Models\DocumentSeries;
use Illuminate\Support\Facades\DB;

/**
 * V7: numeración interna de ventas (VTA-0001) y cotizaciones (COT-0001) con
 * un contador bloqueado en `document_series`, nunca con max(id)+1: dos
 * registros a la vez no reciben el mismo número.
 */
class NumeracionInterna
{
    public const TIPO = 'interno';

    public function siguiente(string $prefijo, string $tabla, string $columna): string
    {
        return DB::transaction(function () use ($prefijo, $tabla, $columna) {
            $serie = $this->serieBloqueada($prefijo, $tabla, $columna);

            // Si un número ya existe (cargado a mano o de antes), se salta.
            do {
                $serie->correlativo_actual++;
                $numero = $prefijo.'-'.str_pad((string) $serie->correlativo_actual, 4, '0', STR_PAD_LEFT);
            } while (DB::table($tabla)->where($columna, $numero)->exists());

            $serie->save();

            return $numero;
        });
    }

    protected function serieBloqueada(string $prefijo, string $tabla, string $columna): DocumentSeries
    {
        $query = fn () => DocumentSeries::query()
            ->where('tipo_comprobante', self::TIPO)
            ->where('serie', $prefijo)
            ->lockForUpdate();

        if ($serie = $query()->first()) {
            return $serie;
        }

        // La primera vez arranca en el mayor número ya usado; el índice
        // único deja crear una sola fila aunque dos lleguen a la vez.
        $ultimo = (int) DB::table($tabla)
            ->where($columna, 'like', $prefijo.'-%')
            ->pluck($columna)
            ->map(fn (string $numero): int => (int) substr($numero, strlen($prefijo) + 1))
            ->max();

        DocumentSeries::query()->createOrFirst(
            ['tipo_comprobante' => self::TIPO, 'serie' => $prefijo],
            ['correlativo_actual' => $ultimo],
        );

        return $query()->firstOrFail();
    }
}
