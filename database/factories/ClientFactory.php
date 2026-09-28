<?php

namespace Database\Factories;

use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Client>
 */
class ClientFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $id = fake()->unique()->numberBetween(1, 9999);

        return [
            'codigo_interno' => 'CLI-'.str_pad((string) $id, 4, '0', STR_PAD_LEFT),
            'tipo_documento' => 'ruc',
            'numero_documento' => fake()->unique()->numerify('20#########'),
            'razon_social' => fake()->company().' S.A.C.',
            'nombre_comercial' => fake()->company(),
            'telefono' => fake()->numerify('044-######'),
            'whatsapp' => fake()->numerify('9########'),
            'email' => fake()->unique()->safeEmail(),
            'direccion_fiscal' => fake()->streetAddress().' - Trujillo',
            'ubigeo' => '130101',
            'estado_contribuyente' => 'ACTIVO',
            'condicion_domicilio' => 'HABIDO',
            'consultado_at' => now(),
            'activo' => true,
            'observaciones' => null,
        ];
    }

    public function dni(): static
    {
        return $this->state(fn (array $attributes) => [
            'tipo_documento' => 'dni',
            'numero_documento' => fake()->unique()->numerify('########'),
            'razon_social' => fake()->name(),
            'nombre_comercial' => null,
            'estado_contribuyente' => null,
            'condicion_domicilio' => null,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'activo' => false,
        ]);
    }
}
