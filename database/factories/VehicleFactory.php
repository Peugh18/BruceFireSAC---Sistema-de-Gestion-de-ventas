<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Vehicle>
 */
class VehicleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'placa' => fake()->unique()->bothify('???-###'),
            'marca' => fake()->randomElement(['Toyota', 'Hyundai', 'Kia', 'Nissan', 'Mitsubishi']),
            'modelo' => fake()->randomElement(['Hilux', 'H100', 'Frontier', 'L200', 'Rio']),
            'descripcion' => 'Unidad de reparto de cliente',
            'estado' => 'activo',
        ];
    }
}
