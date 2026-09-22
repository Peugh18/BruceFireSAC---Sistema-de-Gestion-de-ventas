<?php

namespace App\Actions\Sales;

use App\Models\Sale;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;

class CreateSale
{
    public function __construct(
        protected ProcessSaleItem $processSaleItem,
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
}
