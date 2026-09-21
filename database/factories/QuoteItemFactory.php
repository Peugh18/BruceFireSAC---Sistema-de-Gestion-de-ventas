<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\Quote;
use App\Models\QuoteItem;
use App\Models\Service;
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
            'product_id' => Product::factory(),
            'service_id' => null,
            'cantidad' => $cantidad,
            'precio_unitario' => $precioUnitario,
            'descuento' => 0,
            'subtotal' => $cantidad * $precioUnitario,
        ];
    }

    public function forService(?Service $service = null): static
    {
        return $this->state(fn () => [
            'product_id' => null,
            'service_id' => $service?->id ?? Service::factory(),
        ]);
    }
}
