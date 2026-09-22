<?php

use App\Models\CashRegister;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function createGerenteUserForCashTest(): User
{
    $user = User::factory()->create();
    $user->assignRole('Gerente');

    return $user;
}

test('gerente puede consultar la lista consolidada de cajas de todos los vendedores', function () {
    $gerente = createGerenteUserForCashTest();

    $vendedor1 = User::factory()->create();
    $vendedor1->assignRole('Vendedor');

    $vendedor2 = User::factory()->create();
    $vendedor2->assignRole('Vendedor');

    CashRegister::factory()->create([
        'vendedor_id' => $vendedor1->id,
        'estado' => 'cerrado',
        'monto_apertura' => 100.00,
        'monto_contado_cierre' => 200.00,
        'monto_esperado_calculado' => 200.00,
        'diferencia' => 0.00,
        'fecha_apertura' => today()->subHours(5),
        'fecha_cierre' => today()->subHour(),
    ]);

    CashRegister::factory()->create([
        'vendedor_id' => $vendedor2->id,
        'estado' => 'cerrado',
        'monto_apertura' => 150.00,
        'monto_contado_cierre' => 320.00,
        'monto_esperado_calculado' => 350.00,
        'diferencia' => -30.00,
        'fecha_apertura' => today()->subHours(4),
        'fecha_cierre' => today()->subHour(),
    ]);

    $this->actingAs($gerente)
        ->get(route('gerente.cajas.index', ['current_team' => $gerente->currentTeam]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('gerente/cajas/index')
            ->has('cajas.data', 2)
            ->has('kpis.turnosHoy')
            ->has('kpis.totalDiferenciasMes')
            ->has('kpis.turnosConDescuadreMes')
        );
});

test('vendedor no puede acceder al panel consolidado de cajas de gerente', function () {
    $vendedor = User::factory()->create();
    $vendedor->assignRole('Vendedor');

    $this->actingAs($vendedor)
        ->get(route('gerente.cajas.index', ['current_team' => $vendedor->currentTeam]))
        ->assertForbidden();
});

test('filtro con_diferencia devuelve unicamente turnos con sobrante o faltante', function () {
    $gerente = createGerenteUserForCashTest();
    $vendedor = User::factory()->create();
    $vendedor->assignRole('Vendedor');

    // Caja cuadrada (sin diferencia)
    CashRegister::factory()->create([
        'vendedor_id' => $vendedor->id,
        'estado' => 'cerrado',
        'diferencia' => 0.00,
    ]);

    // Caja descuadrada (con faltante)
    CashRegister::factory()->create([
        'vendedor_id' => $vendedor->id,
        'estado' => 'cerrado',
        'diferencia' => -25.00,
    ]);

    $this->actingAs($gerente)
        ->get(route('gerente.cajas.index', ['current_team' => $gerente->currentTeam, 'con_diferencia' => '1']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('gerente/cajas/index')
            ->has('cajas.data', 1)
            ->where('cajas.data.0.diferencia', -25)
            ->where('cajas.data.0.tipo_diferencia', 'faltante')
        );
});
