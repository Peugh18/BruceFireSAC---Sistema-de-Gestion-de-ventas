<?php

namespace App\Actions\Sales;

use App\Actions\Billing\ConsultarCdrSunat;
use App\Actions\Billing\EmitElectronicDocument;
use App\Actions\Billing\ReserveNextCorrelativo;
use App\Actions\Certificates\EmitirCertificadosDeVenta;
use App\Models\CashRegister;
use App\Models\Certificate;
use App\Models\ElectronicDocument;
use App\Models\Sale;
use App\Models\SalePayment;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Edita todo de una venta ya emitida que SUNAT todavía no tiene por válida
 * (cliente, tipo de comprobante, productos, precios, pago), con el mismo
 * formulario de la venta y sin nota de crédito:
 *
 *  - Factura/boleta por enviar: SUNAT aún no la conoce. Mismo tipo → se
 *    regenera con el mismo número; otro tipo → el número vuelve a su serie
 *    y se toma uno de la otra. Conserva la fecha y la hora de envío.
 *  - Factura/boleta rechazada: no tiene validez; se emite una nueva con otro
 *    número y fecha de hoy (SUNAT no permite reutilizar el número).
 *  - Nota de venta: es interna; conserva su número NV.
 *
 * La venta conserva su número interno. Una factura o boleta aceptada solo se
 * corrige con nota de crédito.
 */
class EditarVentaEmitida
{
    public function __construct(
        protected CreateSale $createSale,
        protected ConfirmSale $confirmSale,
        protected EmitElectronicDocument $emitElectronicDocument,
        protected ReserveNextCorrelativo $reserveNextCorrelativo,
        protected EmitirCertificadosDeVenta $emitirCertificados,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @param  list<array<string, mixed>>  $items
     */
    public function handle(Sale $sale, array $data, array $items, int $userId): Sale
    {
        // Antes de la transacción: el CDR que se recupere de SUNAT debe quedar
        // guardado aunque después se aborte la edición.
        $this->consultarAntesDeReemplazar($sale);

        return DB::transaction(function () use ($sale, $data, $items, $userId) {
            $sale = Sale::query()->with('electronicDocuments', 'items')->lockForUpdate()->findOrFail($sale->id);

            if ($sale->estado !== 'confirmada' || ! $sale->sePuedeEditar()) {
                throw ValidationException::withMessages([
                    'estado' => $sale->estado === 'anulada'
                        ? 'Esta venta está anulada: usa «Rehacer venta».'
                        : 'Este comprobante ya fue aceptado por SUNAT: para corregirlo emite una nota de crédito.',
                ]);
            }

            if ($sale->payments()->whereNotNull('installment_id')->exists()) {
                throw ValidationException::withMessages([
                    'estado' => 'Esta venta ya tiene cuotas cobradas: no se puede editar.',
                ]);
            }

            $documento = $sale->esNotaVenta() ? null : $sale->comprobanteElectronico();

            $antes = [
                ...$sale->only(['client_id', 'comprobante_tipo', 'condicion_pago', 'total', 'destino', 'referencia']),
                'comprobante' => $sale->numeroComprobante(),
                'equipos' => $sale->items->pluck('equipment_id')->filter()->sort()->values()->all(),
            ];

            // El cobro al contado: si se hizo en el turno que sigue abierto se
            // rehace igual que al emitir; si es de un turno anterior se deja
            // donde está y solo se registra hoy la diferencia (lo que el
            // cliente paga de más o se le devuelve), así ningún arqueo
            // descuadra.
            $pagosAntes = $sale->payments()->whereNull('installment_id')->get();
            $turno = CashRegister::abiertaDe((int) $sale->vendedor_id);
            $rehacerCobro = $pagosAntes->isEmpty() || ($turno !== null && $pagosAntes->every(
                fn (SalePayment $pago) => $pago->cash_register_id === $turno->id,
            ));
            $fechaCobro = $pagosAntes->sortBy('id')->first()?->fecha;

            if ($rehacerCobro) {
                // Se rehace el mismo cobro del turno abierto: no es una anulación.
                $sale->payments()->whereNull('installment_id')->forceDelete();
            }

            // La venta vuelve a llenarse como un borrador; la fecha no cambia.
            $sale->update(['estado' => 'borrador']);

            $sale = $this->createSale->actualizar(
                $sale,
                collect($data)->except(['fecha', 'service_order_id'])->all(),
                $items,
                $userId,
            );

            if (! $sale->esNotaVenta()) {
                $this->confirmSale->validarAntesDeEmitir($sale);
            }

            $sale->update(['estado' => 'confirmada']);

            $this->emitirDeNuevo($sale, $documento, $antes['comprobante_tipo']);
            $this->actualizarCertificados($sale->refresh()->load('items'), $antes, $userId);
            if ($rehacerCobro) {
                $this->confirmSale->registrarCobroAlContado($sale);

                // El cobro sigue con la fecha del día en que se hizo.
                if ($fechaCobro) {
                    $sale->payments()->whereNull('installment_id')->update(['fecha' => $fechaCobro]);
                }
            } else {
                $diferencias = $this->confirmSale->cobrosAlContado($sale);

                foreach ($pagosAntes as $pago) {
                    $diferencias[$pago->forma_pago] = round(($diferencias[$pago->forma_pago] ?? 0) - (float) $pago->monto, 2);
                }

                $this->confirmSale->registrarCobros($sale, $diferencias, 'Ajuste por edición de la venta');
            }

            $sale->refresh()->load('electronicDocuments');

            AuditLogger::log(
                action: 'venta.editada',
                entity: $sale,
                oldValues: $antes,
                newValues: [
                    ...$sale->only(['client_id', 'comprobante_tipo', 'condicion_pago', 'total', 'destino', 'referencia']),
                    'comprobante' => $sale->numeroComprobante(),
                ],
                userId: $userId,
            );

            return $sale;
        });
    }

    /**
     * Un comprobante que ya salió hacia SUNAT nunca se reemplaza sin saber qué
     * pasó con él: pudo quedar aceptado y perderse solo la respuesta. Se
     * consulta fuera de la transacción para que el CDR que se recupere quede
     * guardado aunque la edición se aborte después.
     */
    protected function consultarAntesDeReemplazar(Sale $sale): void
    {
        if ($sale->esNotaVenta()) {
            return;
        }

        $documento = $sale->load('electronicDocuments')->comprobanteElectronico();

        if ($documento === null || ! $documento->resultadoDesconocidoDeSunat()) {
            return;
        }

        app(ConsultarCdrSunat::class)->exigirReemplazable($documento);
    }

    /**
     * Deja el comprobante al día con la venta corregida.
     */
    protected function emitirDeNuevo(Sale $sale, ?ElectronicDocument $documento, string $tipoAnterior): void
    {
        $eraNotaVenta = $tipoAnterior === Sale::NOTA_VENTA;

        if ($sale->esNotaVenta()) {
            if ($documento?->estaPorEnviar()) {
                $this->emitElectronicDocument->descartarPorEnviar($documento);
            }

            if (! $sale->numero_nota_venta) {
                $correlativo = $this->reserveNextCorrelativo->handle(Sale::NOTA_VENTA, ConfirmSale::SERIE_NOTA_VENTA);
                $sale->update(['numero_nota_venta' => 'NV-'.str_pad((string) $correlativo, 4, '0', STR_PAD_LEFT)]);
            }

            return;
        }

        if ($eraNotaVenta) {
            $this->liberarNotaVenta($sale);
            $this->emitElectronicDocument->programar($sale, now());

            return;
        }

        if ($documento === null || $documento->fueRechazado()) {
            $this->emitElectronicDocument->programar($sale, now());

            return;
        }

        if ($documento->tipo === $sale->comprobante_tipo) {
            $this->emitElectronicDocument->prepararDocumento($documento);

            return;
        }

        $enviarDesde = $documento->enviar_desde;
        $fechaEmision = $documento->fecha_emision;

        $this->emitElectronicDocument->descartarPorEnviar($documento);
        $nuevo = $this->emitElectronicDocument->programar($sale, $fechaEmision);

        if ($nuevo->estaPorEnviar() && $enviarDesde) {
            $nuevo->update(['enviar_desde' => $enviarDesde]);
        }
    }

    /**
     * Una nota de venta que pasa a factura o boleta deja de existir: su
     * número vuelve a la serie NV.
     */
    protected function liberarNotaVenta(Sale $sale): void
    {
        if (! $sale->numero_nota_venta) {
            return;
        }

        $this->reserveNextCorrelativo->liberar(
            Sale::NOTA_VENTA,
            ConfirmSale::SERIE_NOTA_VENTA,
            (int) substr($sale->numero_nota_venta, 3),
        );

        $sale->update(['numero_nota_venta' => null]);
    }

    /**
     * Los certificados siguen a la venta: si cambió el cliente pasan a su
     * nombre, y si cambiaron los extintores, el destino o la referencia se
     * corrigen (conservan su número). Si ya no hay extintores, se anulan.
     *
     * @param  array<string, mixed>  $antes
     */
    protected function actualizarCertificados(Sale $sale, array $antes, int $userId): void
    {
        $vigentes = Certificate::query()
            ->where('sale_id', $sale->id)
            ->whereIn('estado', ['vigente', 'vencido']);

        if ((int) $antes['client_id'] !== (int) $sale->client_id) {
            (clone $vigentes)->update(['client_id' => $sale->client_id]);
        }

        $equipos = $sale->items->pluck('equipment_id')->filter()->sort()->values()->all();
        $cambio = $equipos !== $antes['equipos']
            || $sale->destino !== $antes['destino']
            || $sale->referencia !== $antes['referencia'];

        if (! $cambio) {
            return;
        }

        if ($equipos === []) {
            $vigentes->update([
                'estado' => 'anulado',
                'anulado_motivo' => "La venta {$sale->numero_interno} se editó y ya no tiene extintores.",
                'anulado_at' => now(),
                'anulado_por' => $userId,
            ]);

            return;
        }

        $this->emitirCertificados->automaticos($sale);
    }
}
