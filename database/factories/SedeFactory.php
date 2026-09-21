<?php

namespace Database\Factories;

use App\Models\Sede;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Sede>
 */
class SedeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombre' => 'Sede '.fake()->unique()->city(),
            'tipo' => 'mixta',
            'ciudad' => fake()->city(),
            'activo' => true,
        ];
    }

    public function almacen(): static
    {
        return $this->state(['tipo' => 'almacen']);
    }

    public function tienda(): static
    {
        return $this->state(['tipo' => 'tienda']);
    }

    public function mixta(): static
    {
        return $this->state(['tipo' => 'mixta']);
    }
}
