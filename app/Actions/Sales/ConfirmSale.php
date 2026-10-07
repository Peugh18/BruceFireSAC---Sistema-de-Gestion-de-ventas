<?php

namespace App\Actions\Sales;

use App\Actions\Billing\EmitElectronicDocument;
use App\Actions\Billing\ReserveNextCorrelativo;
use App\Actions\Certificates\EmitirCertificadosDeVenta;
use App\Models\CashRegister;
use App\Models\CompanySetting;
use App\Models\Sale;
use App\Models\SalePayment;
use App\Models\SaleRefund;
use App\Services\AuditLogger;
use App\Services\Billing\DetraccionCalculator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ConfirmSale
{
    public const SERIE_NOTA_VENTA = 'NV01';

    /** La detracción se registra como un depósito en el Banco de la Nación. */
    public const FORMA_DETRACCION = 'deposito';

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
        // V7: se vuelve a leer bloqueada dentro de la transacción; dos clics a
        // la vez no confirman (ni cobran) dos veces la misma venta.
        return DB::transaction(function () use ($sale) {
            $bloqueada = Sale::query()->lockForUpdate()->find($sale->id);

            if ($bloqueada === null || $bloqueada->estado !== 'borrador') {
                throw ValidationException::withMessages([
                    'estado' => 'Solo se puede confirmar una venta en estado borrador.',
                ]);
            }

            $sale->setRawAttributes($bloqueada->getAttributes(), true);

            return $this->confirmarBloqueada($sale);
        });
    }

    protected function confirmarBloqueada(Sale $sale): Sale
    {
        $sale->loadMissing('client');

        // Un borrador que se emite otro día sale con la fecha en que se
        // emite: el comprobante no puede ir a SUNAT con una fecha vieja.
        // Sus cuotas se corren los mismos días para conservar el plazo pactado.
        if ($sale->fecha->lt(today())) {
            $dias = (int) $sale->fecha->diffInDays(today());

            $sale->installments()->get()->each(
                fn ($cuota) => $cuota->update(['fecha_vencimiento' => $cuota->fecha_vencimiento->copy()->addDays($dias)]),
            );
            $sale->update(['fecha' => today()]);
        }

        if ($sale->esNotaVenta()) {
            return $this->confirmarNotaVenta($sale);
        }

        $this->validarAntesDeEmitir($sale);

        return DB::transaction(function () use ($sale) {
            $sale->update(['estado' => 'confirmada', 'confirmada_at' => now()]);

            $this->emitElectronicDocument->programar($sale);
            $this->emitirCertificados->automaticos($sale);
            $this->registrarCobroAlContado($sale);

            return $sale->refresh();
        });
    }

    /**
     * Reglas de SUNAT que debe cumplir una factura o boleta antes de emitirse.
     */
    public function validarAntesDeEmitir(Sale $sale): void
    {
        $sale->loadMissing('client');

        $this->validarComprobanteCliente->handle($sale->client, $sale->comprobante_tipo, (float) $sale->total, exigirRucHabido: true);

        if ($this->detraccion->paraVenta($sale)['aplica'] && trim((string) CompanySetting::current()->cuenta_detraccion) === '') {
            throw ValidationException::withMessages([
                'estado' => 'Esta factura lleva detracción y falta la cuenta del Banco de la Nación. Pídele al Gerente que la registre en Configuración > Datos de la empresa.',
            ]);
        }
    }

    protected function confirmarNotaVenta(Sale $sale): Sale
    {
        return DB::transaction(function () use ($sale) {
            $correlativo = $this->reserveNextCorrelativo->handle(Sale::NOTA_VENTA, self::SERIE_NOTA_VENTA);

            $sale->update([
                'estado' => 'confirmada',
                'confirmada_at' => now(),
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
    public function registrarCobroAlContado(Sale $sale): void
    {
        if ($sale->esCredito() || ! $sale->medio_pago || $sale->payments()->exists()) {
            return;
        }

        $this->registrarCobros($sale, $this->cobrosAlContado($sale));
    }

    /**
     * Lo que el cliente paga al contado por forma de pago. Con detracción
     * paga el total menos la detracción; esa parte la deposita en la cuenta
     * del Banco de la Nación de la empresa.
     *
     * @return array<string, float>
     */
    public function cobrosAlContado(Sale $sale): array
    {
        if ($sale->esCredito() || ! $sale->medio_pago) {
            return [];
        }

        $detraccion = $this->detraccion->paraVenta($sale)['monto'];
        $cobros = [$sale->medio_pago => round((float) $sale->total - $detraccion, 2)];

        if ($detraccion > 0) {
            $cobros[self::FORMA_DETRACCION] = round(($cobros[self::FORMA_DETRACCION] ?? 0) + $detraccion, 2);
        }

        return $cobros;
    }

    /**
     * Registra hoy los cobros de la venta por forma de pago; un monto
     * negativo es dinero que se le devuelve al cliente. El efectivo que
     * entra exige la caja abierta.
     *
     * @param  array<string, float>  $cobros
     */
    public function registrarCobros(Sale $sale, array $cobros, string $nota = ''): void
    {
        foreach ($cobros as $forma => $monto) {
            $monto = round($monto, 2);

            if ($monto == 0.0) {
                continue;
            }

            if ($monto < 0) {
                // El efectivo que se devuelve también tiene que salir de un turno.
                if ($forma === 'efectivo') {
                    CashRegister::exigirAbiertaParaEfectivo((int) $sale->vendedor_id);
                }

                SaleRefund::create([
                    'sale_id' => $sale->id,
                    'forma_pago' => $forma,
                    'monto' => -$monto,
                    'motivo' => $nota !== '' ? $nota : 'Devolución al cliente',
                    'user_id' => auth()->id(),
                    'fecha' => today(),
                ]);

                continue;
            }

            if ($forma === 'efectivo') {
                CashRegister::exigirAbiertaParaEfectivo((int) $sale->vendedor_id);
            }

            foreach ($this->partesDelCobro($sale, $forma, $monto, $nota) as [$parte, $operacion]) {
                SalePayment::create([
                    'sale_id' => $sale->id,
                    'forma_pago' => $forma,
                    'monto' => $parte,
                    'numero_operacion' => $operacion,
                    'fecha' => today(),
                ]);
            }
        }

        $sale->update(['medio_pago' => null, 'numero_operacion' => null]);
    }

    /**
     * Un cobro puede ser dos registros: si el cliente también paga por
     * depósito, lo suyo (con su número de operación) y la detracción que va
     * al Banco de la Nación quedan separados, como cuando paga por otro medio.
     *
     * @return list<array{0: float, 1: string|null}>
     */
    protected function partesDelCobro(Sale $sale, string $forma, float $monto, string $nota): array
    {
        if ($nota !== '') {
            return [[$monto, $nota]];
        }

        if ($forma !== self::FORMA_DETRACCION) {
            return [[$monto, $sale->numero_operacion]];
        }

        if ($sale->medio_pago !== self::FORMA_DETRACCION) {
            return [[$monto, 'Detracción (Banco de la Nación)']];
        }

        $detraccion = round((float) $this->detraccion->paraVenta($sale)['monto'], 2);

        if ($detraccion <= 0 || $detraccion >= $monto) {
            return [[$monto, $sale->numero_operacion]];
        }

        return [
            [round($monto - $detraccion, 2), $sale->numero_operacion],
            [$detraccion, 'Detracción (Banco de la Nación)'],
        ];
    }
}
