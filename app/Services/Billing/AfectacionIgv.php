<?php

namespace App\Services\Billing;

use App\Models\Product;
use App\Models\Service;
use Illuminate\Validation\ValidationException;

class AfectacionIgv
{
    public static function requiereRevision(Product|Service $catalogo): bool
    {
        return ! in_array($catalogo->tipo_afectacion_igv, ['10', '20', '30'], true)
            || ($catalogo->igv_revisado_at === null && ! $catalogo->aplica_igv && $catalogo->tipo_afectacion_igv === '10');
    }

    public static function codigo(Product|Service $catalogo): string
    {
        if (! in_array($catalogo->tipo_afectacion_igv, ['10', '20', '30'], true) || self::requiereRevision($catalogo)) {
            throw ValidationException::withMessages(['items' => "El Gerente debe elegir la afectación del IGV de {$catalogo->nombre} antes de venderlo."]);
        }

        return (string) $catalogo->tipo_afectacion_igv;
    }
}
