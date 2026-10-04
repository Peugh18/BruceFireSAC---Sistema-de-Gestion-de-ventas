<?php

use App\Models\CashRegister;
use App\Models\Installment;
use App\Models\Sale;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

if (! function_exists('vendedorUser')) {
    function vendedorUser(): User
    {
        $user = User::factory()->create();
        $user->assignRole('Vendedor');

        return $user;
    }
}

test('registerPayment marca el installment como parcial cuando el pago es menor y pagado cuando cubre el total', function () {
    $user = vendedorUser();
    CashRegister::factory()->create(['vendedor_id' => $user->id]);
    $sale = Sale::factory()->create(['estado' => 'confirmada', 'vendedor_id' => $user->id]);

    $installment = Installment::factory()->create([
        'sale_id' => $sale->id,
        'monto' => 300.00,
        'estado' => 'pendiente',
    ]);

    // 1. Pago parcial: 100 de 300
    $response = $this
        ->actingAs($user)
        ->post(route('vendedor.cobranzas.pagar', [
            'current_team' => $user->currentTeam,
            'installment' => $installment,
        ]), [
            'monto' => 100.00,
            'forma_pago' => 'efectivo',
            'numero_operacion' => 'OP-001',
        ]);

    $response->assertSessionHasNoErrors();
    expect($installment->fresh()->estado)->toBe('parcial');

    $this->assertDatabaseHas('sale_payments', [
        'sale_id' => $sale->id,
        'installment_id' => $installment->id,
        'monto' => 100.00,
        'forma_pago' => 'efectivo',
    ]);

    // 2. Pago total restante: 200 de 300
    $response2 = $this
        ->actingAs($user)
        ->post(route('vendedor.cobranzas.pagar', [
            'current_team' => $user->currentTeam,
            'installment' => $installment,
        ]), [
            'monto' => 200.00,
            'forma_pago' => 'transferencia',
            'numero_operacion' => 'OP-002',
        ]);

    $response2->assertSessionHasNoErrors();
    expect($installment->fresh()->estado)->toBe('pagado');

    $this->assertDatabaseHas('sale_payments', [
        'sale_id' => $sale->id,
        'installment_id' => $installment->id,
        'monto' => 200.00,
        'forma_pago' => 'transferencia',
    ]);
});

test('la tarea nocturna marca como vencidas las cuotas atrasadas y abrir cobranzas no cambia datos', function () {
    $user = vendedorUser();
    $sale = Sale::factory()->create(['estado' => 'confirmada', 'vendedor_id' => $user->id]);

    $installmentVencido = Installment::factory()->create([
        'sale_id' => $sale->id,
        'numero_cuota' => 1,
        'monto' => 150.00,
        'fecha_vencimiento' => now()->subDays(5)->toDateString(),
        'estado' => 'pendiente',
    ]);

    $installmentFuturo = Installment::factory()->create([
        'sale_id' => $sale->id,
        'numero_cuota' => 2,
        'monto' => 250.00,
        'fecha_vencimiento' => now()->addDays(10)->toDateString(),
        'estado' => 'pendiente',
    ]);

    $this->actingAs($user)
        ->get(route('vendedor.cobranzas.index', ['current_team' => $user->currentTeam]))
        ->assertOk();
    expect($installmentVencido->fresh()->estado)->toBe('pendiente');

    $this->artisan('alerts:recompute')->assertSuccessful();

    expect($installmentVencido->fresh()->estado)->toBe('vencido')
        ->and($installmentFuturo->fresh()->estado)->toBe('pendiente');

    $this->assertDatabaseHas('installments', [
        'id' => $installmentVencido->id,
        'estado' => 'vencido',
    ]);

    $this->assertDatabaseHas('installments', [
        'id' => $installmentFuturo->id,
        'estado' => 'pendiente',
    ]);
});

test('cobranzas y caja muestran a la vez el turno abierto y las cuotas pendientes', function () {
    $user = vendedorUser();
    $sale = Sale::factory()->create(['estado' => 'confirmada', 'vendedor_id' => $user->id]);
    Installment::factory()->create(['sale_id' => $sale->id, 'monto' => 240, 'estado' => 'pendiente', 'fecha_vencimiento' => now()->addMonth()]);
    CashRegister::factory()->create(['vendedor_id' => $user->id, 'estado' => 'abierto', 'monto_apertura' => 100]);

    foreach (['vendedor.cobranzas.index', 'vendedor.caja.index'] as $ruta) {
        $this->actingAs($user)
            ->get(route($ruta, ['current_team' => $user->currentTeam]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('turno_actual.estado', 'abierto')
                ->has('installments.data', 1));
    }
});

test('las cuotas de un borrador o de una venta anulada no aparecen ni se cobran', function () {
    $user = vendedorUser();
    $team = ['current_team' => $user->currentTeam];

    foreach (['borrador' => 'borrador', 'anulada' => 'anulada'] as $estado) {
        $sale = Sale::factory()->create(['estado' => $estado, 'vendedor_id' => $user->id]);
        $cuota = Installment::factory()->create(['sale_id' => $sale->id, 'monto' => 50, 'estado' => 'parcial', 'fecha_vencimiento' => now()->subDay()]);

        $this->actingAs($user)
            ->post(route('vendedor.cobranzas.pagar', [...$team, 'installment' => $cuota]), ['monto' => 10, 'forma_pago' => 'yape', 'numero_operacion' => '123'])
            ->assertSessionHasErrors('monto');
    }

    $this->actingAs($user)
        ->get(route('vendedor.cobranzas.index', $team))
        ->assertInertia(fn (AssertableInertia $page) => $page->has('installments.data', 0));
});

test('sin caja abierta no se cobra una cuota en efectivo pero si por transferencia', function () {
    $user = vendedorUser();
    $sale = Sale::factory()->create(['estado' => 'confirmada', 'vendedor_id' => $user->id]);
    $cuota = Installment::factory()->create(['sale_id' => $sale->id, 'monto' => 50, 'estado' => 'pendiente', 'fecha_vencimiento' => now()->addDays(5)]);
    $pagar = fn (array $datos) => $this->actingAs($user)->post(route('vendedor.cobranzas.pagar', ['current_team' => $user->currentTeam, 'installment' => $cuota]), $datos);

    $pagar(['monto' => 20, 'forma_pago' => 'efectivo'])->assertSessionHasErrors('forma_pago');
    $pagar(['monto' => 20, 'forma_pago' => 'transferencia', 'numero_operacion' => '998877'])->assertSessionHasNoErrors();

    expect($cuota->fresh()->estado)->toBe('parcial');
});
