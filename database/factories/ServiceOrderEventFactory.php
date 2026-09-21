<?php

namespace Database\Factories;

use App\Models\ServiceOrder;
use App\Models\ServiceOrderEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServiceOrderEvent>
 */
class ServiceOrderEventFactory extends Factory
{
    public function definition(): array
    {
        return [
            'service_order_id' => ServiceOrder::factory(),
            'tipo' => 'creada',
            'user_id' => User::factory(),
            'payload' => ['mensaje' => fake()->sentence()],
            'created_at' => now(),
        ];
    }
}
