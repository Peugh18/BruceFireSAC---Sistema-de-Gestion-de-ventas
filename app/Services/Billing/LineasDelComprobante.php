<?php

namespace App\Services\Billing;

use App\Models\ElectronicDocument;
use App\Models\Sale;
use Throwable;

/**
 * La factura o boleta aceptada de una venta y sus líneas, tal como se
 * enviaron a SUNAT (en el orden del XML). Las notas de crédito y débito se
 * arman sobre esto.
 */
class LineasDelComprobante
{
    public static function original(Sale $sale): ?ElectronicDocument
    {
        return $sale->electronicDocuments
            ->filter(fn (ElectronicDocument $d) => in_array($d->tipo, ['factura', 'boleta'], true) && in_array($d->sunat_estado, ['aceptado', 'observado'], true))
            ->last();
    }

    /**
     * @return list<array{indice: int, nombre: string, cantidad: float, total: float}>
     */
    public static function de(Sale $sale): array
    {
        $original = self::original($sale);

        try {
            $datos = $original ? app(DatosEmision::class)->recuperar($original) : null;
        } catch (Throwable) {
            $datos = null;
        }

        $lineas = [];
        foreach ($datos['lineas'] ?? [] as $indice => $linea) {
            $lineas[] = ['indice' => (int) $indice, 'nombre' => (string) $linea['nombre'], 'cantidad' => (float) $linea['cantidad'], 'total' => round($linea['base'] + $linea['igv'], 2)];
        }

        return $lineas;
    }
}
