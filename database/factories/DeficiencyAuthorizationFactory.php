<?php

namespace Database\Factories;

use App\Models\Deficiency;
use App\Models\DeficiencyAuthorization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DeficiencyAuthorization>
 */
class DeficiencyAuthorizationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'deficiency_id' => Deficiency::factory(),
            'autorizado_por' => fake()->name(),
            'canal' => fake()->randomElement(['whatsapp', 'presencial']),
            'fecha' => now()->toDateString(),
            'observacion' => fake()->optional()->sentence(),
            'vendedor_id' => User::factory(),
        ];
    }
}
