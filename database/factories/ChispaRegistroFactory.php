<?php

namespace Database\Factories;

use App\Models\ChispaRegistro;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChispaRegistro>
 */
class ChispaRegistroFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'rol' => 'Vendedor',
            'pagina' => 'Nueva venta',
            'pregunta' => fake()->sentence(),
            'respuesta' => fake()->sentence(),
            'fuente' => 'gemini',
            'util' => null,
        ];
    }
}
