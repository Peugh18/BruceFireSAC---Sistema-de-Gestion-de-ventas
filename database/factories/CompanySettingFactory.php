<?php

namespace Database\Factories;

use App\Models\CompanySetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CompanySetting>
 */
class CompanySettingFactory extends Factory
{
    public function definition(): array
    {
        return [
            'razon_social' => 'BRUCE FIRE S.A.C.',
            'nombre_comercial' => 'BRUCE FIRE',
            'ruc' => '20600000001',
            'direccion' => fake()->streetAddress().' - Trujillo',
            'ubigeo' => '130101',
            'telefono' => fake()->numerify('044-######'),
            'email' => fake()->companyEmail(),
            'logo_path' => null,
            'leyenda_pie' => null,
        ];
    }
}
