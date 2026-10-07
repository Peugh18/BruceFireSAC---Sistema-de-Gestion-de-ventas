<?php

namespace Database\Factories;

use App\Models\Evidencia;
use App\Models\ServiceOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Evidencia>
 */
class EvidenciaFactory extends Factory
{
    public function definition(): array
    {
        return [
            'service_order_id' => ServiceOrder::factory(),
            'tipo' => 'foto',
            'etapa' => 'recepcion',
            'path' => 'evidencias/prueba/'.fake()->uuid().'.jpg',
            'mime' => 'image/jpeg',
            'tamano' => 1024,
            'created_at' => now(),
        ];
    }
}
