<?php

namespace App\Actions\Billing;

use App\Actions\Sales\RevertSale;
use App\Models\CashRegister;
use App\Models\ElectronicDocument;
use App\Models\Sale;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class IssueCreditNote
{
    /**
     * Motivo 01 del Catálogo 09 SUNAT: anulación de la operación.
     */
    public const MOTIVO_ANULACION = '01';

    /**
     * Motivos del Catálogo 09 que deshacen toda la venta cuando la nota cubre
     * el total: 01 anulación de la operación, 02 anulación por error en el
     * RUC y 06 devolución total. El stock vuelve, las cuotas pendientes se
     * cierran y el cobro al contado se registra como devolución.
     *
     * @var list<string>
     */
    public const MOTIVOS_QUE_ANULAN = ['01', '02', '06'];

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

        return DB::transaction(function () use ($original, $motivoCatalogo09, $detalle, $importe) {
            // Leída de nuevo y bloqueada: la venta pudo anularse desde que se
            // cargó el comprobante, y dos notas a la vez no deben pasar juntas
            // el control del total.
            $sale = Sale::query()->lockForUpdate()->findOrFail($original->sale_id);

            if ($sale->estado === 'anulada') {
                throw ValidationException::withMessages([
                    'electronic_document_id' => 'La venta ya está anulada: no admite más notas de crédito.',
                ]);
            }

            // Una nota que SUNAT rechazó no cuenta: se puede emitir otra.
            $acreditado = (float) ElectronicDocument::query()
                ->where('cpe_afectado_id', $original->id)
                ->where('tipo', 'nota_credito')
                ->whereNotIn('sunat_estado', ['rechazado'])
                ->sum('importe');

            if ($importe + $acreditado > (float) $sale->total + 0.001) {
                throw ValidationException::withMessages([
                    'importe' => 'El importe acumulado de las notas de crédito no puede superar el total del comprobante.',
                ]);
            }

            // Si esta nota anula la venta, al aceptarla SUNAT se devuelve el
            // efectivo cobrado: tiene que caer en la caja abierta del vendedor.
            if (in_array($motivoCatalogo09, self::MOTIVOS_QUE_ANULAN, true)
                && $importe + $acreditado >= (float) $sale->total - 0.001) {
                CashRegister::exigirAbiertaParaDevolverEfectivo($sale);
            }

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

            // La venta se anula recién cuando SUNAT acepta la nota
            // (aplicarSiFueAceptada): si la rechaza, la factura sigue vigente
            // y la venta no debe quedar anulada.
            return $nota;
        });
    }

    /**
     * Cuando SUNAT acepta (u observa) una nota de crédito de anulación,
     * devolución total o error de RUC que, con las demás aceptadas, cubre el
     * total del comprobante, la venta se anula: vuelve el stock, se cierran
     * las cuotas y se registra la devolución del cobro.
     */
    public function aplicarSiFueAceptada(ElectronicDocument $nota): void
    {
        if ($nota->tipo !== 'nota_credito'
            || ! in_array($nota->sunat_estado, ['aceptado', 'observado'], true)
            || ! in_array($nota->motivo_catalogo, self::MOTIVOS_QUE_ANULAN, true)) {
            return;
        }

        DB::transaction(function () use ($nota): void {
            $sale = Sale::query()->lockForUpdate()->findOrFail($nota->sale_id);

            if ($sale->estado === 'anulada') {
                return;
            }

            $aceptado = (float) ElectronicDocument::query()
                ->where('cpe_afectado_id', $nota->cpe_afectado_id)
                ->where('tipo', 'nota_credito')
                ->whereIn('sunat_estado', ['aceptado', 'observado'])
                ->sum('importe');

            if ($aceptado >= (float) $sale->total - 0.001) {
                $this->revertSale->handle($sale);
            }
        });
    }
}
