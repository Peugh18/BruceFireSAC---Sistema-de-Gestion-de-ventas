<?php

namespace App\Actions\Billing;

use App\Models\ElectronicDocument;
use Illuminate\Validation\ValidationException;

class IssueDebitNote
{
    public function __construct(
        protected ReserveNextCorrelativo $reserveNextCorrelativo,
    ) {}

    /**
     * Emite una nota de débito (Catálogo 10 SUNAT) que aumenta el valor de una
     * factura o boleta ya enviada. No modifica stock ni cuotas de la venta.
     */
    public function handle(ElectronicDocument $original, string $motivoCatalogo10, string $detalle, float $importe): ElectronicDocument
    {
        if (! in_array($original->tipo, ['factura', 'boleta'], true)) {
            throw ValidationException::withMessages([
                'electronic_document_id' => 'Solo se puede emitir nota de débito sobre una factura o boleta existente.',
            ]);
        }

        if (! in_array($original->sunat_estado, ['aceptado', 'observado'], true)) {
            throw ValidationException::withMessages([
                'electronic_document_id' => 'La nota de débito solo se emite sobre un comprobante aceptado por SUNAT. Si aún está por enviar o fue rechazado, corrígelo con "Editar comprobante".',
            ]);
        }

        if ($original->sale->estado === 'anulada') {
            throw ValidationException::withMessages([
                'electronic_document_id' => 'La venta está anulada: no admite notas de débito.',
            ]);
        }

        $serie = $original->tipo === 'factura' ? 'FD01' : 'BD01';
        $correlativo = $this->reserveNextCorrelativo->handle('nota_debito', $serie);

        return ElectronicDocument::create([
            'sale_id' => $original->sale_id,
            'tipo' => 'nota_debito',
            'serie' => $serie,
            'correlativo' => $correlativo,
            'cpe_afectado_id' => $original->id,
            'motivo_catalogo' => $motivoCatalogo10,
            'importe' => $importe,
            'sunat_estado' => 'pendiente',
            'sunat_mensaje' => $detalle,
        ]);
    }
}
