<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'codigo' => 'PRD-'.fake()->unique()->numerify('####'),
            'nombre' => fake()->randomElement([
                'Extintor PQS ABC 6 Kg',
                'Extintor CO2 10 Lb',
                'Extintor PQS 12 Kg',
                'Extintor Acetato 6L',
                'Extintor Agua Presurizada 2.5 Gal',
            ]),
            'descripcion' => fake()->optional()->sentence(),
            'unidad_medida' => 'UND',
            'precio_venta' => fake()->randomFloat(2, 40, 250),
            'aplica_igv' => true,
            'tipo_afectacion_igv' => '10',
            'serializado' => true,
            'stock_minimo' => 5,
            'activo' => true,
        ];
    }

    public function serializado(bool $serializado = true): static
    {
        return $this->state(['serializado' => $serializado]);
    }
}
