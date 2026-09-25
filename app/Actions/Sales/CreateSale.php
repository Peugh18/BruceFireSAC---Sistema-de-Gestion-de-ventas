<?php

namespace App\Actions\Sales;

use App\Actions\Cotizaciones\TransitionQuoteState;
use App\Models\Client;
use App\Models\Quote;
use App\Models\Sale;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateSale
{
    public function __construct(
        protected ProcessSaleItem $processSaleItem,
        protected ValidarComprobanteCliente $validarComprobanteCliente,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @param  list<array<string, mixed>>  $items
     */
    public function handle(array $data, array $items, int $vendedorId): Sale
    {
        return DB::transaction(function () use ($data, $items, $vendedorId) {
            $subtotal = 0.0;

            $lineas = array_map(function (array $item) use (&$subtotal) {
                $descuento = $item['descuento'] ?? 0;
                $lineaSubtotal = ($item['cantidad'] * $item['precio_unitario']) - $descuento;
                $subtotal += $lineaSubtotal;

                return [...$item, 'descuento' => $descuento, 'subtotal' => $lineaSubtotal];
            }, $items);

            $igv = round($subtotal * 0.18, 2);
            $total = $subtotal + $igv;

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
                $this->convertirCotizacion((int) $data['quote_id'], (int) $sale->client_id);
            }

            if ($sale->condicion_pago === 'credito_30') {
                $sale->installments()->create([
                    'numero_cuota' => 1,
                    'fecha_vencimiento' => $sale->fecha->copy()->addDays(30),
                    'monto' => $total,
                    'estado' => 'pendiente',
                ]);
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
     * Una venta solo puede nacer de una cotización aceptada del mismo
     * cliente; al crearse la venta, la cotización pasa a convertida.
     */
    protected function convertirCotizacion(int $quoteId, int $clientId): void
    {
        $quote = Quote::query()->lockForUpdate()->findOrFail($quoteId);

        if ($quote->estado !== 'aceptada') {
            throw ValidationException::withMessages([
                'quote_id' => "La cotización {$quote->numero} no está aceptada; solo una cotización aceptada se puede pasar a venta.",
            ]);
        }

        if ((int) $quote->client_id !== $clientId) {
            throw ValidationException::withMessages([
                'quote_id' => "La cotización {$quote->numero} pertenece a otro cliente.",
            ]);
        }

        app(TransitionQuoteState::class)->handle($quote, 'convertida');
    }
}
