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
    $sale = Sale::factory()->create(['vendedor_id' => $user->id]);

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

test('un installment vencido se actualiza a vencido al listar via CollectionController index', function () {
    $user = vendedorUser();
    $sale = Sale::factory()->create(['vendedor_id' => $user->id]);

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

    $response = $this
        ->actingAs($user)
        ->get(route('vendedor.cobranzas.index', ['current_team' => $user->currentTeam]));

    $response->assertOk();

    // Verificamos que se haya actualizado en BD a 'vencido'
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
    $sale = Sale::factory()->create(['vendedor_id' => $user->id]);
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
