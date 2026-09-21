<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\ServiceOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServiceOrder>
 */
class ServiceOrderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'codigo' => 'OT-'.fake()->year().'-'.fake()->unique()->numerify('####'),
            'client_id' => Client::factory(),
            'tipo_servicio' => fake()->randomElement(['Recarga y mantenimiento', 'Instalación', 'Inspección técnica']),
            'fecha' => fake()->dateTimeBetween('-1 month', 'now'),
            'prioridad' => 'normal',
            'estado' => 'pendiente_recepcion',
        ];
    }
}
