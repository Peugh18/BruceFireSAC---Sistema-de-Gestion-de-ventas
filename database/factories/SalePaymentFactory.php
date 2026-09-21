<?php

namespace Database\Factories;

use App\Models\Sale;
use App\Models\SalePayment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SalePayment>
 */
class SalePaymentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'sale_id' => Sale::factory(),
            'forma_pago' => fake()->randomElement(['efectivo', 'transferencia', 'yape', 'plin', 'pos', 'deposito', 'otro']),
            'monto' => fake()->randomFloat(2, 50, 500),
            'numero_operacion' => fake()->optional()->numerify('OP-######'),
            'fecha' => fake()->dateTimeBetween('-1 month', 'now'),
        ];
    }
}
