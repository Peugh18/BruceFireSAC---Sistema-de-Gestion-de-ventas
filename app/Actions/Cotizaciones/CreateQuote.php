<?php

namespace App\Actions\Cotizaciones;

use App\Models\Product;
use App\Models\Quote;
use App\Models\Service;
use App\Services\Billing\AfectacionIgv;
use App\Services\Billing\PrecioConIgv;
use App\Services\NumeracionInterna;
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
            // Precios con IGV incluido, igual que en la venta. La afectación se
            // copia del catálogo y se guarda en la línea para que la cotización
            // calcule sus totales con MISMA regla que la venta: si no, una
            // cotización con líneas exoneradas mostraría un IGV inventado y al
            // convertirla en venta los totales no cuadrarían con lo cotizado.
            $lineas = array_map(function (array $item) {
                $descuento = $item['descuento'] ?? 0;

                $catalogo = ! empty($item['service_id']) ? Service::findOrFail((int) $item['service_id']) : Product::findOrFail((int) ($item['product_id'] ?? 0));
                $afectacion = AfectacionIgv::codigo($catalogo);

                return [...$item, 'tipo_afectacion_igv' => $afectacion, 'descuento' => $descuento, 'subtotal' => round(($item['cantidad'] * $item['precio_unitario']) - $descuento, 2)];
            }, $items);

            ['subtotal' => $subtotal, 'igv' => $igv, 'total' => $total] = PrecioConIgv::totalesConAfectacion($lineas);

            $quote = Quote::create([
                ...$data,
                'numero' => app(NumeracionInterna::class)->siguiente('COT', 'quotes', 'numero'),
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
