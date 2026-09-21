<?php

namespace App\Actions\Cotizaciones;

use App\Models\Quote;
use Illuminate\Support\Facades\DB;

class CreateQuote
{
    /**
     * @param  array<string, mixed>  $data
     * @param  list<array{catalog_item_id:int,cantidad:int,precio_unitario:float,descuento?:float}>  $items
     */
    public function handle(array $data, array $items, int $vendedorId): Quote
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

            $quote = Quote::create([
                ...$data,
                'numero' => 'COT-'.str_pad((string) (Quote::max('id') + 1), 4, '0', STR_PAD_LEFT),
                'vendedor_id' => $vendedorId,
                'subtotal' => $subtotal,
                'igv' => $igv,
                'total' => $subtotal + $igv,
                'estado' => 'borrador',
            ]);

            foreach ($lineas as $linea) {
                $quote->items()->create($linea);
            }

            return $quote->load('items.catalogItem');
        });
    }
}
