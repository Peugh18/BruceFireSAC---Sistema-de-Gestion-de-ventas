<?php

namespace App\Actions\Billing;

use App\Models\ElectronicDocument;
use Illuminate\Validation\ValidationException;

class IssueCreditNote
{
    public function __construct(
        protected ReserveNextCorrelativo $reserveNextCorrelativo,
    ) {}

    public function handle(ElectronicDocument $original, string $motivoCatalogo09, string $detalle, float $importe): ElectronicDocument
    {
        if (! in_array($original->tipo, ['factura', 'boleta'], true)) {
            throw ValidationException::withMessages([
                'electronic_document_id' => 'Solo se puede emitir nota de crédito sobre una factura o boleta existente.',
            ]);
        }

        $serie = config('billing.series.nota_credito', $original->tipo === 'factura' ? 'FC01' : 'BC01');
        $correlativo = $this->reserveNextCorrelativo->handle('nota_credito', $serie);

        return ElectronicDocument::create([
            'sale_id' => $original->sale_id,
            'tipo' => 'nota_credito',
            'serie' => $serie,
            'correlativo' => $correlativo,
            'cpe_afectado_id' => $original->id,
            'motivo_catalogo' => $motivoCatalogo09,
            'sunat_estado' => 'pendiente',
            'sunat_mensaje' => $detalle,
        ]);
    }
}
