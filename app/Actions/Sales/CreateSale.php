<?php

namespace App\Actions\Sales;

use App\Actions\Cotizaciones\TransitionQuoteState;
use App\Models\Client;
use App\Models\Quote;
use App\Models\Sale;
use App\Services\AuditLogger;
use App\Services\Billing\PrecioConIgv;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateSale
{
    public function __construct(
        protected ProcessSaleItem $processSaleItem,
        protected ValidarComprobanteCliente $validarComprobanteCliente,
        protected LiberarBorrador $liberarBorrador,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @param  list<array<string, mixed>>  $items
     */
    public function handle(array $data, array $items, int $vendedorId): Sale
    {
        return DB::transaction(function () use ($data, $items, $vendedorId) {
            // Los precios ya incluyen IGV: el subtotal de cada línea es lo que
            // paga el cliente y la base/IGV se separan de ahí.
            $lineas = array_map(function (array $item) {
                $descuento = $item['descuento'] ?? 0;

                return [...$item, 'descuento' => $descuento, 'subtotal' => round(($item['cantidad'] * $item['precio_unitario']) - $descuento, 2)];
            }, $items);

            ['subtotal' => $subtotal, 'igv' => $igv, 'total' => $total] = PrecioConIgv::totales(array_column($lineas, 'subtotal'));

            $this->validarComprobanteCliente->handle(
                Client::query()->findOrFail($data['client_id']),
                (string) ($data['comprobante_tipo'] ?? ''),
                $total,
            );

            $sale = Sale::create([
                ...$data,
                'numero_interno' => 'VTA-'.str_pad((string) (Sale::max('id') + 1), 4, '0', STR_PAD_LEFT),
                'vendedor_id' => $vendedorId,
                'subtotal' => $subtotal,
                'igv' => $igv,
                'total' => $total,
                'estado' => 'borrador',
            ]);

            foreach ($lineas as $linea) {
                $this->processSaleItem->handle($sale, $linea);
            }

            if (! empty($data['quote_id'])) {
                $this->convertirCotizacion((int) $data['quote_id'], (int) $sale->client_id, (int) $sale->sede_id);
            }

            if ($sale->esCredito()) {
                $this->crearCuotas($sale, $data['cuotas'] ?? []);
            }

            AuditLogger::log(
                action: 'venta.creada',
                entity: $sale,
                newValues: [
                    'numero_interno' => $sale->numero_interno,
                    'total' => $total,
                    'comprobante_tipo' => $sale->comprobante_tipo,
                ],
                userId: $vendedorId
            );

            return $sale->load('items.product', 'items.service', 'items.equipment', 'items.inventoryUnit');
        });
    }

    /**
     * Vuelve a llenar una venta en borrador con los datos corregidos: libera
     * lo que tenía reservado y procesa las líneas de nuevo. Conserva su
     * número, su vendedor y su cotización de origen.
     *
     * @param  array<string, mixed>  $data
     * @param  list<array<string, mixed>>  $items
     */
    public function actualizar(Sale $sale, array $data, array $items, int $userId): Sale
    {
        return DB::transaction(function () use ($sale, $data, $items, $userId) {
            $sale = Sale::query()->lockForUpdate()->findOrFail($sale->id);
            $antes = $sale->only(['client_id', 'comprobante_tipo', 'condicion_pago', 'total']);

            $lineas = array_map(function (array $item) {
                $descuento = $item['descuento'] ?? 0;

                return [...$item, 'descuento' => $descuento, 'subtotal' => round(($item['cantidad'] * $item['precio_unitario']) - $descuento, 2)];
            }, $items);

            ['subtotal' => $subtotal, 'igv' => $igv, 'total' => $total] = PrecioConIgv::totales(array_column($lineas, 'subtotal'));

            $this->validarComprobanteCliente->handle(
                Client::query()->findOrFail($data['client_id']),
                (string) ($data['comprobante_tipo'] ?? ''),
                $total,
            );

            $this->liberarBorrador->handle($sale);

            $sale->update([
                ...collect($data)->except(['quote_id', 'vendedor_id', 'cuotas'])->all(),
                'subtotal' => $subtotal,
                'igv' => $igv,
                'total' => $total,
            ]);

            foreach ($lineas as $linea) {
                $this->processSaleItem->handle($sale, $linea);
            }

            if ($sale->esCredito()) {
                $this->crearCuotas($sale, $data['cuotas'] ?? []);
            }

            AuditLogger::log(
                action: 'venta.borrador_editado',
                entity: $sale,
                oldValues: $antes,
                newValues: $sale->only(['client_id', 'comprobante_tipo', 'condicion_pago', 'total']),
                userId: $userId
            );

            return $sale->refresh();
        });
    }

    /**
     * Cuotas de una venta a crédito: plazo y cantidad libres, pero la suma
     * debe ser exactamente el total (SUNAT lo valida en la factura).
     *
     * @param  list<array{fecha_vencimiento: string, monto: float|int|string}>  $cuotas
     */
    protected function crearCuotas(Sale $sale, array $cuotas): void
    {
        if ($cuotas === []) {
            $cuotas = [['fecha_vencimiento' => $sale->fecha->copy()->addDays(30)->toDateString(), 'monto' => $sale->total]];
        }

        $suma = round(array_sum(array_map(fn (array $cuota) => (float) $cuota['monto'], $cuotas)), 2);

        if (abs($suma - (float) $sale->total) > 0.009) {
            throw ValidationException::withMessages([
                'cuotas' => 'La suma de las cuotas (S/ '.number_format($suma, 2).') debe ser igual al total de la venta (S/ '.number_format((float) $sale->total, 2).').',
            ]);
        }

        usort($cuotas, fn (array $a, array $b) => strcmp($a['fecha_vencimiento'], $b['fecha_vencimiento']));

        foreach (array_values($cuotas) as $index => $cuota) {
            $sale->installments()->create([
                'numero_cuota' => $index + 1,
                'fecha_vencimiento' => $cuota['fecha_vencimiento'],
                'monto' => round((float) $cuota['monto'], 2),
                'estado' => 'pendiente',
            ]);
        }
    }

    /**
     * Una venta solo puede nacer de una cotización aceptada del mismo
     * cliente; al crearse la venta, la cotización pasa a convertida.
     */
    protected function convertirCotizacion(int $quoteId, int $clientId, int $sedeId): void
    {
        $quote = Quote::query()->lockForUpdate()->findOrFail($quoteId);

        if ($quote->sede_id !== null && (int) $quote->sede_id !== $sedeId) {
            throw ValidationException::withMessages([
                'quote_id' => "La cotización {$quote->numero} es de otra sede.",
            ]);
        }

        if ((int) $quote->client_id !== $clientId) {
            throw ValidationException::withMessages([
                'quote_id' => "La cotización {$quote->numero} pertenece a otro cliente.",
            ]);
        }

        app(TransitionQuoteState::class)->convertToSale($quote);
    }
}
