<?php

namespace App\Services\Billing;

class ResponseClassifier
{
    /**
     * Clasifica la respuesta de SUNAT según el código del CDR. Código 0 con
     * notas de observación no es "aceptado" sin más: SUNAT lo marca como
     * observado aunque el comprobante tenga validez legal.
     *
     * @param  list<string>  $notas
     */
    public function classify(int $codigo, array $notas = []): string
    {
        if ($codigo === 0) {
            return $notas === [] ? 'aceptado' : 'observado';
        }

        if ($codigo >= 2000 && $codigo <= 3999) {
            return 'rechazado';
        }

        if ($codigo >= 100 && $codigo <= 1999) {
            return 'excepcion';
        }

        return 'observado';
    }
}
