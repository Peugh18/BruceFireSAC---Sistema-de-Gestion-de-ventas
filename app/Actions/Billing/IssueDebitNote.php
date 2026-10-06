<?php

namespace App\Actions\Billing;

use App\Models\ElectronicDocument;
use Illuminate\Validation\ValidationException;

class IssueDebitNote
{
    /**
     * Motivos del Catalogo 10 que usan afectacion inafecta (30) segun la
     * R.S. 000048-2026 vigente desde el 1/08/2026.
     *
     * @var list<string>
     */
    public const MOTIVOS_INAFECTOS = ['13'];

    public function __construct(
        protected ReserveNextCorrelativo $reserveNextCorrelativo,
    ) {}

    /**
     * Emite una nota de debito (Catalogo 10 SUNAT) que aumenta el valor de una
     * factura o boleta ya enviada. No modifica stock ni cuotas de la venta.
     */
    public function handle(ElectronicDocument $original, string $motivoCatalogo10, string $detalle, float $importe): ElectronicDocument
    {
        $this->validar($original);

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
            // S6: la fecha se guarda al crear y se reutiliza en cada reintento.
            'fecha_emision' => now(),
        ]);
    }

    /**
     * Reglas que se revisan al pedir la nota (antes de que la apruebe el
     * Gerente) y otra vez al emitirla.
     *
     * @throws ValidationException
     */
    public function validar(ElectronicDocument $original): void
    {
        if (! in_array($original->tipo, ['factura', 'boleta'], true)) {
            throw ValidationException::withMessages([
                'electronic_document_id' => 'Solo se puede emitir nota de debito sobre una factura o boleta existente.',
            ]);
        }

        if (! in_array($original->sunat_estado, ['aceptado', 'observado'], true)) {
            throw ValidationException::withMessages([
                'electronic_document_id' => 'La nota de debito solo se emite sobre un comprobante aceptado por SUNAT. Si aun esta por enviar o fue rechazado, corrigelo con Editar desde la venta.',
            ]);
        }

        if ($original->sale()->value('estado') === 'anulada') {
            throw ValidationException::withMessages([
                'electronic_document_id' => 'La venta esta anulada: no admite notas de debito.',
            ]);
        }
    }
}
