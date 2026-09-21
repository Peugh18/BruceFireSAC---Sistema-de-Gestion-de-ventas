<?php

namespace Database\Factories;

use App\Models\CatalogItem;
use App\Models\Sale;
use App\Models\SaleItem;
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
            'catalog_item_id' => CatalogItem::factory()->producto(),
            'tipo_linea' => 'unidad_nueva',
            'cantidad' => $cantidad,
            'precio_unitario' => $precioUnitario,
            'descuento' => $descuento,
            'subtotal' => ($cantidad * $precioUnitario) - $descuento,
        ];
    }
}
