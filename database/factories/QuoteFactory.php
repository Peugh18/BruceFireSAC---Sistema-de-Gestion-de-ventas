<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\Quote;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Quote>
 */
class QuoteFactory extends Factory
{
    public function definition(): array
    {
        $subtotal = fake()->randomFloat(2, 100, 3000);
        $igv = round($subtotal * 0.18, 2);

        return [
            'numero' => 'COT-'.fake()->unique()->numerify('####'),
            'vendedor_id' => User::factory(),
            'client_id' => Client::factory(),
            'fecha' => fake()->dateTimeBetween('-1 month', 'now'),
            'vigencia_hasta' => fake()->dateTimeBetween('now', '+1 month'),
            'subtotal' => $subtotal,
            'igv' => $igv,
            'total' => $subtotal + $igv,
            'estado' => 'borrador',
        ];
    }

    public function enviada(): static
    {
        return $this->state(['estado' => 'enviada']);
    }

    public function aceptada(): static
    {
        return $this->state(['estado' => 'aceptada']);
    }

    public function vencida(): static
    {
        return $this->state(['estado' => 'vencida', 'vigencia_hasta' => now()->subDay()]);
    }
}
