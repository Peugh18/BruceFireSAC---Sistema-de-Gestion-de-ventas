<?php

namespace Database\Factories;

use App\Models\CatalogItem;
use App\Models\InventoryUnit;
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
            'catalog_item_id' => CatalogItem::factory()->producto(),
            'sede_almacen_id' => Sede::factory()->almacen(),
            'numero_serie' => 'BF-'.fake()->year().'-'.fake()->unique()->numerify('####'),
            'estado' => 'disponible',
            'fecha_ingreso' => fake()->dateTimeBetween('-1 year', 'now'),
        ];
    }

    public function vendido(): static
    {
        return $this->state(['estado' => 'vendido']);
    }
}
