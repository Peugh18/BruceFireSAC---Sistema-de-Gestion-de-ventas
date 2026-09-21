<?php

namespace Database\Factories;

use App\Models\CashRegister;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CashRegister>
 */
class CashRegisterFactory extends Factory
{
    public function definition(): array
    {
        return [
            'vendedor_id' => User::factory(),
            'sede_id' => null,
            'fecha_apertura' => now()->subHours(2),
            'monto_apertura' => 100.00,
            'fecha_cierre' => null,
            'monto_contado_cierre' => null,
            'monto_esperado_calculado' => null,
            'diferencia' => null,
            'observacion' => null,
            'estado' => 'abierto',
        ];
    }

    public function cerrado(): static
    {
        return $this->state(fn (array $attributes) => [
            'fecha_cierre' => now(),
            'monto_contado_cierre' => 100.00,
            'monto_esperado_calculado' => 100.00,
            'diferencia' => 0.00,
            'estado' => 'cerrado',
        ]);
    }
}
