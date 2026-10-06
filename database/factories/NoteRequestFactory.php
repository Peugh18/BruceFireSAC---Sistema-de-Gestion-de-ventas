<?php

namespace Database\Factories;

use App\Models\ElectronicDocument;
use App\Models\NoteRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NoteRequest>
 */
class NoteRequestFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'electronic_document_id' => ElectronicDocument::factory()->state(['sunat_estado' => 'aceptado']),
            'tipo' => 'nota_credito',
            'motivo_catalogo' => '07',
            'detalle' => fake()->sentence(),
            'importe' => 10,
            'estado' => 'por_aprobar',
            'solicitado_por' => User::factory(),
        ];
    }
}
