<?php

namespace Database\Factories;

use App\Models\Deficiency;
use App\Models\Equipment;
use App\Models\ServiceOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Deficiency>
 */
class DeficiencyFactory extends Factory
{
    public function definition(): array
    {
        return [
            'service_order_id' => ServiceOrder::factory(),
            'equipment_id' => Equipment::factory(),
            'componente' => fake()->randomElement(['Manómetro', 'Válvula', 'Manguera', 'Cilindro']),
            'condicion' => fake()->randomElement(['Dañado', 'Vencido', 'Con fuga', 'Ausente']),
            'nota' => fake()->optional()->sentence(),
            'accion_recomendada' => fake()->optional()->sentence(),
            'requiere_autorizacion' => true,
            'estado' => 'esperando_autorizacion',
        ];
    }
}
