<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\Equipment;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Equipment>
 */
class EquipmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'product_id' => Product::factory(),
            'tipo_agente' => 'PQS ABC',
            'numero_serie' => 'EQ-'.fake()->year().'-'.fake()->unique()->numerify('####'),
            'fecha_venta' => fake()->dateTimeBetween('-1 year', 'now'),
            'ubicacion_actual' => fake()->optional()->streetAddress(),
            'estado' => 'activo',
            'proxima_fecha_atencion' => now()->addYear(),
            'proxima_prueba_hidrostatica' => null,
        ];
    }
}
