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
            'ubigeo' => '130101',
            'activo' => true,
        ];
    }

    public function almacen(): static
    {
        return $this->state(['tipo' => 'almacen']);
    }

    public function tienda(): static
    {
        // Una tienda siempre saca stock de un almacén (SedeObserver lo exige).
        return $this->state(['tipo' => 'tienda', 'almacen_id' => Sede::factory()->almacen()]);
    }

    public function mixta(): static
    {
        return $this->state(['tipo' => 'mixta']);
    }

    public function taller(): static
    {
        return $this->state(['tipo' => 'taller', 'almacen_id' => null]);
    }
}
