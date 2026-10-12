<?php

use App\Models\Sale;
use App\Models\SalePayment;
use App\Models\SaleRefund;
use App\Models\User;
use App\Services\Cobranzas\CarteraDeCobranzas;
use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('lo cobrado del mes descuenta lo devuelto a clientes', function () {
    $venta = Sale::factory()->create();
    SalePayment::factory()->create(['sale_id' => $venta->id, 'monto' => 505, 'fecha' => now()]);
    SaleRefund::create(['sale_id' => $venta->id, 'forma_pago' => 'efectivo', 'monto' => 55, 'motivo' => 'Devolución', 'fecha' => now()]);

    expect(CarteraDeCobranzas::cobrado(now()->startOfMonth(), now()->endOfMonth()))->toBe(450.0);
});

test('el panel del gerente y cobranzas muestran el mismo monto cobrado', function () {
    $venta = Sale::factory()->create();
    SalePayment::factory()->create(['sale_id' => $venta->id, 'monto' => 200, 'fecha' => now()]);
    SaleRefund::create(['sale_id' => $venta->id, 'forma_pago' => 'efectivo', 'monto' => 20, 'motivo' => 'Devolución', 'fecha' => now()]);

    $gerente = User::factory()->create();
    $gerente->assignRole('Gerente');
    $equipo = ['current_team' => $gerente->currentTeam];

    $this->actingAs($gerente)
        ->get(route('gerente.dashboard', $equipo))
        ->assertInertia(fn (Assert $page) => $page->where('metrics.montoCobrado', 180));

    $this->actingAs($gerente)
        ->get(route('gerente.cobranzas.index', $equipo))
        ->assertInertia(fn (Assert $page) => $page->where('kpis.cobradoEsteMes', 180));
});
