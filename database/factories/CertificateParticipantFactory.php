<?php

namespace Database\Factories;

use App\Models\Certificate;
use App\Models\CertificateParticipant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CertificateParticipant>
 */
class CertificateParticipantFactory extends Factory
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
            'orden' => 1,
            'nombres' => fake()->name(),
            'dni' => fake()->optional()->numerify('########'),
            'cargo' => null,
            'sufijo' => 1,
            'qr_token' => (string) Str::uuid(),
            'anulado_at' => null,
        ];
    }
}
