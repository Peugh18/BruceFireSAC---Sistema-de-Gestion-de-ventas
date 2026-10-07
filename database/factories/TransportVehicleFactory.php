<?php

namespace Database\Factories;

use App\Models\TransportVehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TransportVehicle>
 */
class TransportVehicleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'placa' => fake()->unique()->bothify('???###'),
            'categoria' => 'N',
            'descripcion' => 'Camioneta de reparto',
            'activo' => true,
        ];
    }
}
