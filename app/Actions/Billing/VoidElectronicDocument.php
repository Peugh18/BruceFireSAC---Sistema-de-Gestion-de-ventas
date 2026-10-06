<?php

namespace App\Actions\Billing;

use App\Models\ElectronicDocument;
use Illuminate\Validation\ValidationException;

class VoidElectronicDocument
{
    /**
     * Limite legal SUNAT para comunicacion de baja (7 dias calendario).
     */
    public const DIAS_LIMITE_BAJA = 7;

    /**
     * Procesa la comunicacion de baja de un comprobante emitido y aceptado.
     *
     * @throws ValidationException
     */
    public function handle(ElectronicDocument $document, string $motivo): ElectronicDocument
    {
        if ($document->sunat_estado !== 'aceptado') {
            throw ValidationException::withMessages([
                'electronic_document' => 'Solo se pueden anular comprobantes que hayan sido aceptados por SUNAT.',
            ]);
        }

        $fechaEmision = $document->fecha_emision ?? $document->created_at ?? now();

        if ($fechaEmision->diffInDays(now()) > self::DIAS_LIMITE_BAJA) {
            throw ValidationException::withMessages([
                'electronic_document' => 'No se puede anular un comprobante emitido hace mas de 7 dias.',
            ]);
        }

        if (! in_array($document->tipo, ['factura', 'nota_credito', 'nota_debito'], true)) {
            throw ValidationException::withMessages([
                'electronic_document' => 'Solo se permite comunicacion de baja para facturas y sus notas asociadas.',
            ]);
        }

        $document->update([
            'sunat_estado' => 'anulado',
            'sunat_mensaje' => "Baja procesada por SUNAT: {$motivo}",
        ]);

        return $document->refresh();
    }
}
