<?php

namespace Database\Factories;

use App\Models\CatalogItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CatalogItem>
 */
class CatalogItemFactory extends Factory
{
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
                'Recarga y mantenimiento CO2 10 Lb',
                'Recarga y mantenimiento PQS 6 Kg',
            ]),
            'tipo' => fake()->randomElement(['producto', 'servicio']),
            'unidad_medida' => 'UND',
            'precio_venta' => fake()->randomFloat(2, 40, 250),
            'aplica_igv' => true,
            'activo' => true,
        ];
    }

    public function producto(): static
    {
        return $this->state(['tipo' => 'producto']);
    }

    public function servicio(): static
    {
        return $this->state(['tipo' => 'servicio']);
    }
}
