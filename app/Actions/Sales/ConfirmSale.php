<?php

namespace App\Actions\Sales;

use App\Actions\Billing\EmitElectronicDocument;
use App\Actions\Billing\ReserveNextCorrelativo;
use App\Actions\Certificates\EmitirCertificadosDeVenta;
use App\Models\CompanySetting;
use App\Models\Sale;
use App\Models\SalePayment;
use App\Services\AuditLogger;
use App\Services\Billing\DetraccionCalculator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ConfirmSale
{
    public const SERIE_NOTA_VENTA = 'NV01';

    public function __construct(
        protected EmitElectronicDocument $emitElectronicDocument,
        protected ValidarComprobanteCliente $validarComprobanteCliente,
        protected ReserveNextCorrelativo $reserveNextCorrelativo,
        protected EmitirCertificadosDeVenta $emitirCertificados,
        protected DetraccionCalculator $detraccion,
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
     *
     * En los dos casos los certificados de los extintores salen solos, listos
     * para imprimir; la vendedora solo los ajusta si hace falta.
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

        if ($this->detraccion->paraVenta($sale)['aplica'] && trim((string) CompanySetting::current()->cuenta_detraccion) === '') {
            throw ValidationException::withMessages([
                'estado' => 'Esta factura lleva detracción y falta la cuenta del Banco de la Nación. Pídele al Gerente que la registre en Configuración > Datos de la empresa.',
            ]);
        }

        return DB::transaction(function () use ($sale) {
            $sale->update(['estado' => 'confirmada']);

            $this->emitElectronicDocument->programar($sale);
            $this->emitirCertificados->automaticos($sale);
            $this->registrarCobroAlContado($sale);

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

            $this->emitirCertificados->automaticos($sale);
            $this->registrarCobroAlContado($sale);

            AuditLogger::log(
                action: 'venta.nota_venta_confirmada',
                entity: $sale,
                newValues: ['numero_nota_venta' => $sale->numero_nota_venta, 'total' => $sale->total],
                userId: auth()->id()
            );

            return $sale->refresh();
        });
    }

    /**
     * Una venta al contado ya está pagada: al emitirla se registra el cobro
     * con el medio que eligió el cliente, así la caja cuadra sola. Las de
     * crédito se cobran por cuotas en Cobranzas.
     */
    protected function registrarCobroAlContado(Sale $sale): void
    {
        if ($sale->esCredito() || ! $sale->medio_pago || $sale->payments()->exists()) {
            return;
        }

        // Con detracción el cliente paga el total menos la detracción; esa
        // parte la deposita en la cuenta del Banco de la Nación de la empresa.
        $detraccion = $this->detraccion->paraVenta($sale)['monto'];

        SalePayment::create([
            'sale_id' => $sale->id,
            'forma_pago' => $sale->medio_pago,
            'monto' => round((float) $sale->total - $detraccion, 2),
            'numero_operacion' => $sale->numero_operacion,
            'fecha' => today(),
        ]);

        $sale->update(['medio_pago' => null, 'numero_operacion' => null]);

        if ($detraccion > 0) {
            SalePayment::create([
                'sale_id' => $sale->id,
                'forma_pago' => 'deposito',
                'monto' => $detraccion,
                'numero_operacion' => 'Detracción (Banco de la Nación)',
                'fecha' => today(),
            ]);
        }
    }
}
