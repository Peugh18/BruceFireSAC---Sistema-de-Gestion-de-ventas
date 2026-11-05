<?php

namespace Database\Factories;

use App\Models\InventoryUnit;
use App\Models\Product;
use App\Models\Sede;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryUnit>
 */
class InventoryUnitFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'sede_almacen_id' => Sede::factory()->almacen(),
            'numero_serie' => 'BF-'.fake()->year().'-'.fake()->unique()->numerify('####'),
            // Un extintor de stock trae sus datos de fábrica: sin ellos no se certifica.
            'marca' => 'BRUCE',
            'serie_fabricante' => fake()->bothify('SF-######'),
            'anio_fabricacion' => (string) (now()->year - 1),
            'estado' => 'disponible',
            'fecha_ingreso' => fake()->dateTimeBetween('-1 year', 'now'),
        ];
    }

    public function vendido(): static
    {
        return $this->state(['estado' => 'vendido']);
    }
}
