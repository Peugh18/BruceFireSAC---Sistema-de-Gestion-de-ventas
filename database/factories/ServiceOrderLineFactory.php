<?php

namespace Database\Factories;

use App\Models\Service;
use App\Models\ServiceOrder;
use App\Models\ServiceOrderLine;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ServiceOrderLine> */
class ServiceOrderLineFactory extends Factory
{
    protected $model = ServiceOrderLine::class;

    public function definition(): array
    {
        return [
            'service_order_id' => ServiceOrder::factory(),
            'service_id' => Service::factory(),
            'cantidad' => 1,
            'quote_item_id' => null,
            'adicional' => false,
        ];
    }
}
