<?php

namespace Database\Factories;

use App\Models\ElectronicDocument;
use App\Models\Sale;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ElectronicDocument>
 */
class ElectronicDocumentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'sale_id' => Sale::factory(),
            'tipo' => 'factura',
            'serie' => 'F001',
            'correlativo' => fake()->unique()->numberBetween(1, 999999),
            'sunat_estado' => 'pendiente',
        ];
    }
}
