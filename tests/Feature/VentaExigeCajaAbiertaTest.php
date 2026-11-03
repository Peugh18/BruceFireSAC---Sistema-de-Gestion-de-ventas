<?php

use App\Actions\Cash\OpenCashRegister;
use App\Models\Installment;
use App\Models\Sale;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia as Assert;

/*
| Toda venta del vendedor cae en un turno de caja, como en un punto de venta
| (Odoo POS): sin turno abierto no se emite, aunque se pague con Yape o a crédito.
*/

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->vendedor = User::factory()->create();
    $this->vendedor->assignRole('Vendedor');
    $this->borrador = Sale::factory()->create([
        'vendedor_id' => $this->vendedor->id,
        'estado' => 'borrador',
        'condicion_pago' => 'credito',
        'comprobante_tipo' => 'nota_venta',
        'total' => 300,
    ]);
    Installment::factory()->create(['sale_id' => $this->borrador->id, 'monto' => 300, 'estado' => 'pendiente', 'fecha_vencimiento' => today()->addDays(15)]);
    $this->confirmar = fn () => $this->actingAs($this->vendedor)->post(route('vendedor.ventas.confirmar', [
        'current_team' => $this->vendedor->currentTeam, 'sale' => $this->borrador,
    ]));
});

test('sin turno de caja abierto no se emite una venta aunque no sea en efectivo', function () {
    ($this->confirmar)()->assertSessionHasErrors('caja');

    expect($this->borrador->fresh()->estado)->toBe('borrador');
});

test('con el turno abierto la misma venta se emite', function () {
    app(OpenCashRegister::class)->handle($this->vendedor, null, 0);

    ($this->confirmar)()->assertSessionHasNoErrors();

    expect($this->borrador->fresh()->estado)->not->toBe('borrador');
});

test('la pantalla de nueva venta sabe que la caja está cerrada', function () {
    $this->actingAs($this->vendedor)
        ->get(route('vendedor.ventas.create', ['current_team' => $this->vendedor->currentTeam]))
        ->assertInertia(fn (Assert $page) => $page->where('caja_abierta', false));
});
