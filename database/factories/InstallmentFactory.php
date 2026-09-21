<?php

namespace Database\Factories;

use App\Models\Installment;
use App\Models\Sale;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Installment>
 */
class InstallmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'sale_id' => Sale::factory(),
            'numero_cuota' => 1,
            'fecha_vencimiento' => now()->addDays(30),
            'monto' => fake()->randomFloat(2, 100, 3000),
            'estado' => 'pendiente',
        ];
    }
}
