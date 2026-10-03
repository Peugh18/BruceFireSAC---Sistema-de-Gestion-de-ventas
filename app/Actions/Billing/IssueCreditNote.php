<?php

namespace App\Actions\Billing;

use App\Actions\Sales\RevertSale;
use App\Models\ElectronicDocument;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class IssueCreditNote
{
    /**
     * Motivo 01 del Catálogo 09 SUNAT: anulación de la operación.
     */
    public const MOTIVO_ANULACION = '01';

    public function __construct(
        protected ReserveNextCorrelativo $reserveNextCorrelativo,
        protected RevertSale $revertSale,
    ) {}

    public function handle(ElectronicDocument $original, string $motivoCatalogo09, string $detalle, float $importe): ElectronicDocument
    {
        if (! in_array($original->tipo, ['factura', 'boleta'], true)) {
            throw ValidationException::withMessages([
                'electronic_document_id' => 'Solo se puede emitir nota de crédito sobre una factura o boleta existente.',
            ]);
        }

        if (! in_array($original->sunat_estado, ['aceptado', 'observado'], true)) {
            throw ValidationException::withMessages([
                'electronic_document_id' => 'La nota de crédito solo se emite sobre un comprobante aceptado por SUNAT. Si aún está por enviar o fue rechazado, corrígelo con «Editar» desde la venta.',
            ]);
        }

        $sale = $original->sale;

        if ($sale->estado === 'anulada') {
            throw ValidationException::withMessages([
                'electronic_document_id' => 'La venta ya está anulada: no admite más notas de crédito.',
            ]);
        }

        $acreditado = (float) ElectronicDocument::query()
            ->where('cpe_afectado_id', $original->id)
            ->where('tipo', 'nota_credito')
            ->sum('importe');

        if ($importe + $acreditado > (float) $sale->total + 0.001) {
            throw ValidationException::withMessages([
                'importe' => 'El importe acumulado de las notas de crédito no puede superar el total del comprobante.',
            ]);
        }

        return DB::transaction(function () use ($original, $sale, $motivoCatalogo09, $detalle, $importe, $acreditado) {
            $serie = $original->tipo === 'factura' ? 'FC01' : 'BC01';
            $correlativo = $this->reserveNextCorrelativo->handle('nota_credito', $serie);

            $nota = ElectronicDocument::create([
                'sale_id' => $original->sale_id,
                'tipo' => 'nota_credito',
                'serie' => $serie,
                'correlativo' => $correlativo,
                'cpe_afectado_id' => $original->id,
                'motivo_catalogo' => $motivoCatalogo09,
                'importe' => $importe,
                'sunat_estado' => 'pendiente',
                'sunat_mensaje' => $detalle,
            ]);

            $esAnulacionTotal = $motivoCatalogo09 === self::MOTIVO_ANULACION
                && $importe + $acreditado >= (float) $sale->total - 0.001;

            if ($esAnulacionTotal) {
                $this->revertSale->handle($sale);
            }

            return $nota;
        });
    }
}
