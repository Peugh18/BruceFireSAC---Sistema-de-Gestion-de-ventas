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

function createGerenteUserForDashboardTest(): User
{
    $user = User::factory()->create();
    $user->assignRole('Gerente');

    return $user;
}

test('gerente accede al dashboard y recibe las 12 metricas y 6 graficos', function () {
    $user = createGerenteUserForDashboardTest();

    $this->actingAs($user)
        ->get(route('gerente.dashboard', ['current_team' => $user->currentTeam]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('gerente/dashboard')
            ->has('metrics', fn (Assert $metrics) => $metrics
                ->has('ventasDia')
                ->has('ventasMes')
                ->has('facturacionMes')
                ->has('montoCobrado')
                ->has('cuentasPorCobrar')
                ->has('vencidoPorCobrar')
                ->has('cotizacionesPendientes')
                ->has('tasaConversion')
                ->has('ordenesEnProceso')
                ->has('equiposProximosAtencion')
                ->has('stockCritico')
                ->has('documentosSunatError')
            )
            ->has('charts', fn (Assert $charts) => $charts
                ->has('ventasMensuales')
                ->has('ventasPorItem')
                ->has('serviciosPorTipo')
                ->has('carteraPorEstado')
                ->has('topClientes')
                ->has('productosMayorMovimiento')
            )
        );
});

test('dashboard generico redirige a usuarios con rol Gerente a gerente.dashboard', function () {
    $user = createGerenteUserForDashboardTest();

    $this->actingAs($user)
        ->get(route('dashboard', ['current_team' => $user->currentTeam]))
        ->assertRedirect(route('gerente.dashboard', ['current_team' => $user->currentTeam]));
});

test('dashboard de gerente calcula metricas con datos reales', function () {
    $user = createGerenteUserForDashboardTest();
    $client = Client::factory()->create();

    // Venta de hoy
    $sale = Sale::factory()->create([
        'client_id' => $client->id,
        'vendedor_id' => $user->id,
        'fecha' => today(),
        'total' => 1500.00,
        'comprobante_tipo' => 'factura',
        'estado' => 'confirmada',
    ]);

    // Cuota vencida
    Installment::factory()->create([
        'sale_id' => $sale->id,
        'monto' => 500.00,
        'fecha_vencimiento' => today()->subDays(5),
        'estado' => 'pendiente',
    ]);

    // Pago de mes
    SalePayment::factory()->create([
        'sale_id' => $sale->id,
        'monto' => 1000.00,
        'fecha' => today(),
    ]);

    $this->actingAs($user)
        ->get(route('gerente.dashboard', ['current_team' => $user->currentTeam]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('gerente/dashboard')
            ->where('metrics.ventasDia', 1500)
            ->where('metrics.montoCobrado', 1000)
            ->where('metrics.vencidoPorCobrar', 500)
        );
});
