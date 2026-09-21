<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\Sale;
use App\Models\Sede;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Sale>
 */
class SaleFactory extends Factory
{
    public function definition(): array
    {
        $subtotal = fake()->randomFloat(2, 100, 3000);
        $igv = round($subtotal * 0.18, 2);

        return [
            'numero_interno' => 'VTA-'.fake()->unique()->numerify('####'),
            'client_id' => Client::factory(),
            'sede_id' => Sede::factory()->almacen(),
            'vendedor_id' => User::factory(),
            'fecha' => fake()->dateTimeBetween('-1 month', 'now'),
            'destino' => 'local_cliente',
            'condicion_pago' => 'contado',
            'comprobante_tipo' => 'factura',
            'subtotal' => $subtotal,
            'igv' => $igv,
            'total' => $subtotal + $igv,
            'estado' => 'borrador',
            'observaciones' => null,
        ];
    }
}
