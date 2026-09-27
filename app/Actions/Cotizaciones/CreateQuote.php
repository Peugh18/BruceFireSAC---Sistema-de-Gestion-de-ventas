<?php

namespace App\Actions\Cotizaciones;

use App\Models\Quote;
use App\Services\Billing\PrecioConIgv;
use Illuminate\Support\Facades\DB;

class CreateQuote
{
    /**
     * @param  array<string, mixed>  $data
     * @param  list<array{product_id?:int,service_id?:int,cantidad:int,precio_unitario:float,descuento?:float}>  $items
     */
    public function handle(array $data, array $items, int $vendedorId): Quote
    {
        return DB::transaction(function () use ($data, $items, $vendedorId) {
            // Precios con IGV incluido, igual que en la venta.
            $lineas = array_map(function (array $item) {
                $descuento = $item['descuento'] ?? 0;

                return [...$item, 'descuento' => $descuento, 'subtotal' => round(($item['cantidad'] * $item['precio_unitario']) - $descuento, 2)];
            }, $items);

            ['subtotal' => $subtotal, 'igv' => $igv, 'total' => $total] = PrecioConIgv::totales(array_column($lineas, 'subtotal'));

            $quote = Quote::create([
                ...$data,
                'numero' => 'COT-'.str_pad((string) (Quote::max('id') + 1), 4, '0', STR_PAD_LEFT),
                'vendedor_id' => $vendedorId,
                'subtotal' => $subtotal,
                'igv' => $igv,
                'total' => $total,
                'estado' => 'borrador',
            ]);

            foreach ($lineas as $linea) {
                $quote->items()->create($linea);
            }

            return $quote->load('items.product', 'items.service');
        });
    }
}
