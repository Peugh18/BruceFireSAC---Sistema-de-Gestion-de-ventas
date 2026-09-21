<?php

namespace Database\Factories;

use App\Models\Certificate;
use App\Models\CertificateType;
use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Certificate>
 */
class CertificateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'numero' => 'OG-'.now()->year.'-'.fake()->unique()->numerify('####'),
            'certificate_type_id' => CertificateType::factory(),
            'client_id' => Client::factory(),
            'fecha_emision' => now()->toDateString(),
            'fecha_vigencia_hasta' => now()->addYear()->toDateString(),
            'estado' => 'vigente',
            'qr_token' => (string) Str::uuid(),
            'sale_id' => null,
            'service_order_id' => null,
        ];
    }
}
