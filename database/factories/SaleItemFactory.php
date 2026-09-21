<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SaleItem>
 */
class SaleItemFactory extends Factory
{
    public function definition(): array
    {
        $cantidad = fake()->numberBetween(1, 3);
        $precioUnitario = fake()->randomFloat(2, 50, 500);
        $descuento = 0;

        return [
            'sale_id' => Sale::factory(),
            'product_id' => Product::factory(),
            'service_id' => null,
            'tipo_linea' => 'unidad_nueva',
            'cantidad' => $cantidad,
            'precio_unitario' => $precioUnitario,
            'descuento' => $descuento,
            'subtotal' => ($cantidad * $precioUnitario) - $descuento,
        ];
    }

    public function forService(?Service $service = null): static
    {
        return $this->state(fn () => [
            'product_id' => null,
            'service_id' => $service?->id ?? Service::factory(),
            'tipo_linea' => 'recarga_servicio',
        ]);
    }
}
