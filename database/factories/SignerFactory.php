<?php

namespace Database\Factories;

use App\Models\Signer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Signer>
 */
class SignerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombre' => fake()->name(),
            'cargo' => 'Administrador',
            'cip' => null,
            'especialidad' => null,
            'firma_path' => null,
            'sello_path' => null,
            'activo' => true,
        ];
    }

    /**
     * Ingeniero colegiado (firma pozo a tierra y protocolos).
     */
    public function ingeniero(): static
    {
        return $this->state(fn () => [
            'cargo' => 'Ingeniero mecánico electricista',
            'cip' => (string) fake()->numberBetween(100000, 299999),
            'especialidad' => 'Mecánica eléctrica',
        ]);
    }
}
