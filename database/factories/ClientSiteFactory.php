<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\ClientSite;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClientSite>
 */
class ClientSiteFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'tipo' => fake()->randomElement(['oficina', 'tienda', 'planta', 'almacen', 'local', 'sucursal', 'otra']),
            'nombre' => 'Local '.fake()->city(),
            'direccion' => fake()->streetAddress().' - Trujillo',
            'ubigeo' => '130101',
            'referencia' => 'A dos cuadras de '.fake()->streetName(),
            'contacto' => fake()->name(),
            'telefono' => fake()->numerify('044-######'),
            'email' => fake()->safeEmail(),
            'estado' => 'activo',
        ];
    }
}
