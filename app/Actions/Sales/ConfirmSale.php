<?php

namespace App\Actions\Sales;

use App\Actions\Billing\EmitElectronicDocument;
use App\Actions\Billing\ReserveNextCorrelativo;
use App\Models\Sale;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ConfirmSale
{
    public const SERIE_NOTA_VENTA = 'NV01';

    public function __construct(
        protected EmitElectronicDocument $emitElectronicDocument,
        protected ValidarComprobanteCliente $validarComprobanteCliente,
        protected ReserveNextCorrelativo $reserveNextCorrelativo,
    ) {}

    /**
     * Confirma una venta en borrador: valida que el RUC del cliente esté
     * Activo y Habido (regla dura SUNAT, ver Documento Maestro §78.1) y,
     * si el comprobante es Factura o Boleta, lo deja "por enviar": se envía a
     * SUNAT al vencer la ventana de revisión (billing.envio_diferido_horas).
     * No aplica a Boleta con DNI: las personas naturales no tienen estado de
     * contribuyente.
     *
     * Una nota de venta es una venta interna: no valida el RUC, no se envía
     * a SUNAT y recibe una numeración propia NV independiente de F001/B001.
     */
    public function handle(Sale $sale): Sale
    {
        if ($sale->estado !== 'borrador') {
            throw ValidationException::withMessages([
                'estado' => 'Solo se puede confirmar una venta en estado borrador.',
            ]);
        }

        $sale->loadMissing('client');

        if ($sale->esNotaVenta()) {
            return $this->confirmarNotaVenta($sale);
        }

        $this->validarComprobanteCliente->handle($sale->client, $sale->comprobante_tipo, (float) $sale->total, exigirRucHabido: true);

        return DB::transaction(function () use ($sale) {
            $sale->update(['estado' => 'confirmada']);

            $this->emitElectronicDocument->programar($sale);

            return $sale->refresh();
        });
    }

    protected function confirmarNotaVenta(Sale $sale): Sale
    {
        return DB::transaction(function () use ($sale) {
            $correlativo = $this->reserveNextCorrelativo->handle(Sale::NOTA_VENTA, self::SERIE_NOTA_VENTA);

            $sale->update([
                'estado' => 'confirmada',
                'numero_nota_venta' => 'NV-'.str_pad((string) $correlativo, 4, '0', STR_PAD_LEFT),
            ]);

            AuditLogger::log(
                action: 'venta.nota_venta_confirmada',
                entity: $sale,
                newValues: ['numero_nota_venta' => $sale->numero_nota_venta, 'total' => $sale->total],
                userId: auth()->id()
            );

            return $sale->refresh();
        });
    }
}
