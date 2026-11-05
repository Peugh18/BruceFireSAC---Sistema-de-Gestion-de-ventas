<?php

use App\Models\CashRegister;
use App\Models\Installment;
use App\Models\Sale;
use App\Models\SalePayment;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->vendedor = User::factory()->create();
    $this->vendedor->assignRole('Vendedor');
    $this->equipo = ['current_team' => $this->vendedor->currentTeam];
});

function cuotaDelVendedor(User $vendedor, string $vence, float $monto): Installment
{
    $sale = Sale::factory()->create(['vendedor_id' => $vendedor->id, 'estado' => 'confirmada', 'condicion_pago' => 'credito']);

    return Installment::factory()->create(['sale_id' => $sale->id, 'monto' => $monto, 'monto_acreditado' => 0, 'estado' => 'pendiente', 'fecha_vencimiento' => $vence]);
}

test('cobranzas resume lo vencido, lo de los proximos 7 dias y filtra la lista', function () {
    cuotaDelVendedor($this->vendedor, today()->subDays(3)->toDateString(), 100);
    cuotaDelVendedor($this->vendedor, today()->addDays(2)->toDateString(), 50);
    cuotaDelVendedor($this->vendedor, today()->addDays(30)->toDateString(), 20);

    $this->actingAs($this->vendedor)
        ->get(route('vendedor.cobranzas.index', $this->equipo))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('resumen.vencido.monto', 100)
            ->where('resumen.vencido.cuotas', 1)
            ->where('resumen.semana.monto', 50)
            ->where('resumen.total.monto', 170)
            ->where('resumen.total.cuotas', 3)
            ->has('installments.data', 3));

    $this->get(route('vendedor.cobranzas.index', [...$this->equipo, 'filtro' => 'vencidas']))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('filtro', 'vencidas')->has('installments.data', 1)->where('installments.data.0.saldo', 100));
});

test('mi caja lista los movimientos del turno y el efectivo va sin monto', function () {
    $turno = CashRegister::factory()->create(['vendedor_id' => $this->vendedor->id, 'estado' => 'abierto', 'monto_apertura' => 200]);
    $sale = Sale::factory()->create(['vendedor_id' => $this->vendedor->id, 'estado' => 'confirmada']);
    SalePayment::factory()->create(['sale_id' => $sale->id, 'forma_pago' => 'efectivo', 'monto' => 80, 'cash_register_id' => $turno->id]);
    SalePayment::factory()->create(['sale_id' => $sale->id, 'forma_pago' => 'transferencia', 'monto' => 45, 'cash_register_id' => $turno->id]);

    $this->actingAs($this->vendedor)
        ->get(route('vendedor.caja.index', $this->equipo))
        ->assertInertia(function (AssertableInertia $page) {
            $movimientos = collect($page->toArray()['props']['turno_actual']['movimientos']);

            expect($movimientos->firstWhere('detalle', 'Efectivo')['monto'])->toBeNull()
                ->and($movimientos->firstWhere('detalle', 'Transferencia')['monto'])->toEqual(45)
                ->and($movimientos->firstWhere('tipo', 'apertura')['monto'])->toEqual(200);
        });
});
