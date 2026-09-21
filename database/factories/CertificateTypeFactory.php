<?php

namespace Database\Factories;

use App\Models\CertificateType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CertificateType>
 */
class CertificateTypeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'codigo' => fake()->unique()->randomElement([
                'operatividad_garantia',
                'prueba_hidrostatica',
                'informe_deteccion',
                'lamina_seguridad',
            ]),
            'nombre' => fake()->words(3, true),
            'vigencia_meses' => 12,
            'generado_por_rol' => 'tecnico_planta',
        ];
    }
}
