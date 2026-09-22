<?php

namespace Database\Factories;

use App\Models\AuditLog;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AuditLog>
 */
class AuditLogFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'team_id' => Team::factory(),
            'action' => fake()->randomElement([
                'venta.creada',
                'stock.ajuste',
                'orden.cerrada',
                'certificado.emitido',
                'configuracion.empresa_actualizada',
            ]),
            'auditable_type' => null,
            'auditable_id' => null,
            'old_values' => ['estado' => 'pendiente'],
            'new_values' => ['estado' => 'completado'],
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
            'context' => ['motivo' => fake()->sentence()],
            'created_at' => now(),
        ];
    }
}
