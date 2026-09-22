<?php

use App\Models\Client;
use App\Models\Installment;
use App\Models\Sale;
use App\Models\SalePayment;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function createGerenteUserForCollectionTest(): User
{
    $user = User::factory()->create();
    $user->assignRole('Gerente');

    return $user;
}

test('gerente puede consultar la cartera consolidada de cobranzas con kpis', function () {
    $gerente = createGerenteUserForCollectionTest();

    $vendedor = User::factory()->create();
    $vendedor->assignRole('Vendedor');

    $client = Client::factory()->create(['razon_social' => 'EMPRESA CLIENTE S.A.']);

    $sale = Sale::factory()->create([
        'client_id' => $client->id,
        'vendedor_id' => $vendedor->id,
        'total' => 1000.00,
    ]);

    $installment = Installment::factory()->create([
        'sale_id' => $sale->id,
        'numero_cuota' => 1,
        'monto' => 500.00,
        'fecha_vencimiento' => today()->addDays(5),
        'estado' => 'pendiente',
    ]);

    SalePayment::factory()->create([
        'sale_id' => $sale->id,
        'installment_id' => $installment->id,
        'monto' => 200.00,
        'fecha' => today(),
    ]);

    $this->actingAs($gerente)
        ->get(route('gerente.cobranzas.index', ['current_team' => $gerente->currentTeam]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('gerente/cobranzas/index')
            ->has('cuotas.data', 1)
            ->where('cuotas.data.0.monto_pagado', 200)
            ->where('cuotas.data.0.saldo_pendiente', 300)
            ->has('kpis.totalPorCobrar')
            ->has('kpis.vencidoTotal')
            ->has('kpis.cobradoEsteMes')
            ->has('kpis.clientesConDeuda')
        );
});

test('vendedor no puede acceder al panel consolidado de cobranzas de gerente', function () {
    $vendedor = User::factory()->create();
    $vendedor->assignRole('Vendedor');

    $this->actingAs($vendedor)
        ->get(route('gerente.cobranzas.index', ['current_team' => $vendedor->currentTeam]))
        ->assertForbidden();
});

test('cuotas vencidas son actualizadas automaticamente y filtradas correctamente', function () {
    $gerente = createGerenteUserForCollectionTest();
    $sale = Sale::factory()->create();

    // Cuota vencida
    $vencida = Installment::factory()->create([
        'sale_id' => $sale->id,
        'fecha_vencimiento' => today()->subDays(10),
        'estado' => 'pendiente',
    ]);

    // Cuota futura
    $futura = Installment::factory()->create([
        'sale_id' => $sale->id,
        'fecha_vencimiento' => today()->addDays(20),
        'estado' => 'pendiente',
    ]);

    $this->actingAs($gerente)
        ->get(route('gerente.cobranzas.index', ['current_team' => $gerente->currentTeam, 'estado' => 'vencido']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('gerente/cobranzas/index')
            ->has('cuotas.data', 1)
            ->where('cuotas.data.0.id', $vencida->id)
        );

    // Verificar en BD que el estado se actualizó automáticamente
    $this->assertDatabaseHas('installments', [
        'id' => $vencida->id,
        'estado' => 'vencido',
    ]);
});
