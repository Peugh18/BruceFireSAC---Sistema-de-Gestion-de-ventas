<?php

namespace Database\Factories;

use App\Models\ProductCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductCategory>
 */
class ProductCategoryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $nombre = fake()->unique()->lexify('categoria ????');

        return [
            'clave' => str($nombre)->slug('_')->toString(),
            'nombre' => ucfirst($nombre),
            'genera_alertas_vencimiento' => false,
            'activo' => true,
        ];
    }
}
