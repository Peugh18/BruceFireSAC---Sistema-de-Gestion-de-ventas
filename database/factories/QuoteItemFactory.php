<?php

namespace Database\Factories;

use App\Models\CatalogItem;
use App\Models\Quote;
use App\Models\QuoteItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QuoteItem>
 */
class QuoteItemFactory extends Factory
{
    public function definition(): array
    {
        $cantidad = fake()->numberBetween(1, 5);
        $precioUnitario = fake()->randomFloat(2, 40, 250);

        return [
            'quote_id' => Quote::factory(),
            'catalog_item_id' => CatalogItem::factory(),
            'cantidad' => $cantidad,
            'precio_unitario' => $precioUnitario,
            'descuento' => 0,
            'subtotal' => $cantidad * $precioUnitario,
        ];
    }
}
