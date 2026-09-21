<?php

use App\Actions\Cash\CloseCashRegister;
use App\Actions\Cash\OpenCashRegister;
use App\Models\CashRegister;
use App\Models\Sale;
use App\Models\SalePayment;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Validation\ValidationException;

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

test('abrir turno cuando ya hay uno abierto para el mismo vendedor falla con ValidationException', function () {
    $user = vendedorUser();

    CashRegister::factory()->create([
        'vendedor_id' => $user->id,
        'estado' => 'abierto',
    ]);

    // Test a nivel de Action
    expect(fn () => app(OpenCashRegister::class)->handle($user, null, 100.0))
        ->toThrow(ValidationException::class);

    // Test a nivel de HTTP
    $response = $this
        ->actingAs($user)
        ->post(route('vendedor.caja.abrir', ['current_team' => $user->currentTeam]), [
            'monto_apertura' => 50.0,
        ]);

    $response->assertSessionHasErrors('cash_register');
});

test('cerrar turno calcula monto_esperado_calculado sumando solo SalePayments en efectivo del vendedor en su turno', function () {
    $user = vendedorUser();
    $otroUser = vendedorUser();

    $cashRegister = CashRegister::factory()->create([
        'vendedor_id' => $user->id,
        'fecha_apertura' => now()->subHours(3),
        'monto_apertura' => 100.00,
        'estado' => 'abierto',
    ]);

    $sale = Sale::factory()->create(['vendedor_id' => $user->id]);
    $otroSale = Sale::factory()->create(['vendedor_id' => $otroUser->id]);

    // 1. Pago en efectivo del vendedor durante el turno (DEBE CONTAR)
    SalePayment::factory()->create([
        'sale_id' => $sale->id,
        'forma_pago' => 'efectivo',
        'monto' => 150.00,
        'created_at' => now()->subHours(2),
    ]);

    // 2. Pago por transferencia del vendedor durante el turno (NO DEBE CONTAR)
    SalePayment::factory()->create([
        'sale_id' => $sale->id,
        'forma_pago' => 'transferencia',
        'monto' => 200.00,
        'created_at' => now()->subHours(2),
    ]);

    // 3. Pago en efectivo de OTRO vendedor (NO DEBE CONTAR)
    SalePayment::factory()->create([
        'sale_id' => $otroSale->id,
        'forma_pago' => 'efectivo',
        'monto' => 500.00,
        'created_at' => now()->subHours(2),
    ]);

    // 4. Pago en efectivo del vendedor antes de la apertura del turno (NO DEBE CONTAR)
    SalePayment::factory()->create([
        'sale_id' => $sale->id,
        'forma_pago' => 'efectivo',
        'monto' => 70.00,
        'created_at' => now()->subHours(5),
    ]);

    $closed = app(CloseCashRegister::class)->handle($cashRegister, 250.00, 'Cierre conforme');

    // Esperado: monto_apertura (100) + efectivo (150) = 250.00
    expect((float) $closed->monto_esperado_calculado)->toEqual(250.00)
        ->and((float) $closed->diferencia)->toEqual(0.00)
        ->and($closed->estado)->toBe('cerrado')
        ->and($closed->observacion)->toBe('Cierre conforme');
});

test('la diferencia se calcula como monto_contado_cierre menos el esperado con signo correcto', function () {
    $user = vendedorUser();

    $cashRegisterSobrante = CashRegister::factory()->create([
        'vendedor_id' => $user->id,
        'fecha_apertura' => now()->subHours(1),
        'monto_apertura' => 100.00,
        'estado' => 'abierto',
    ]);

    // Esperado = 100. Contado = 120. Diferencia = +20 (sobrante)
    $closedSobrante = app(CloseCashRegister::class)->handle($cashRegisterSobrante, 120.00);
    expect((float) $closedSobrante->diferencia)->toEqual(20.00);

    // Faltante: Esperado = 100. Contado = 80. Diferencia = -20 (faltante)
    $cashRegisterFaltante = CashRegister::factory()->create([
        'vendedor_id' => $user->id,
        'fecha_apertura' => now()->subHours(1),
        'monto_apertura' => 100.00,
        'estado' => 'abierto',
    ]);

    $closedFaltante = app(CloseCashRegister::class)->handle($cashRegisterFaltante, 80.00);
    expect((float) $closedFaltante->diferencia)->toEqual(-20.00);
});

test('otro vendedor no puede cerrar el turno de otro vendedor', function () {
    $userA = vendedorUser();
    $userB = vendedorUser();

    $cashRegister = CashRegister::factory()->create([
        'vendedor_id' => $userA->id,
        'monto_apertura' => 100.00,
        'estado' => 'abierto',
    ]);

    $response = $this
        ->actingAs($userB)
        ->post(route('vendedor.caja.cerrar', [
            'current_team' => $userB->currentTeam,
            'cash_register' => $cashRegister,
        ]), [
            'monto_contado_cierre' => 100.00,
        ]);

    $response->assertStatus(403);
    expect($cashRegister->fresh()->estado)->toBe('abierto');
});
