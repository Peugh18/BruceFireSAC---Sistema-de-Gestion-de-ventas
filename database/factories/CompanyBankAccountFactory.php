<?php

namespace Database\Factories;

use App\Models\CompanyBankAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CompanyBankAccount>
 */
class CompanyBankAccountFactory extends Factory
{
    public function definition(): array
    {
        return [
            'banco' => fake()->randomElement(['BCP', 'BBVA', 'Interbank', 'Scotiabank']),
            'titular' => 'BRUCE FIRE S.A.C.',
            'numero_cuenta' => fake()->numerify('###-########-##-##'),
            'cci' => fake()->numerify('##############'),
            'moneda' => 'PEN',
            'activo' => true,
            'orden' => 0,
        ];
    }
}
