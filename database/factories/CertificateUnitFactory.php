<?php

namespace Database\Factories;

use App\Models\Certificate;
use App\Models\CertificateUnit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CertificateUnit>
 */
class CertificateUnitFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'certificate_id' => Certificate::factory(),
            'equipment_id' => null,
            'numero_serie_snapshot' => 'EXT-'.fake()->unique()->numerify('#####'),
            'fecha_ultima_ph' => null,
            'fecha_ultima_recarga' => now()->toDateString(),
        ];
    }
}
