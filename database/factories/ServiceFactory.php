<?php

namespace Database\Factories;

use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Service>
 */
class ServiceFactory extends Factory
{
    protected $model = Service::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'codigo' => 'SRV-'.fake()->unique()->numerify('####'),
            'nombre' => fake()->randomElement([
                'Recarga y mantenimiento CO2 10 Lb',
                'Recarga y mantenimiento PQS 6 Kg',
                'Prueba hidrostática extintor',
                'Inspección técnica de seguridad',
            ]),
            'descripcion' => fake()->optional()->sentence(),
            'unidad_medida' => 'ZZ',
            'precio_venta' => fake()->randomFloat(2, 30, 150),
            'aplica_igv' => true,
            'tipo_afectacion_igv' => '10',
            'activo' => true,
        ];
    }
}
