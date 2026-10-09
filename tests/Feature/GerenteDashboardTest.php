<?php

use App\Models\Client;
use App\Models\ElectronicDocument;
use App\Models\Installment;
use App\Models\Sale;
use App\Models\SalePayment;
use App\Models\User;
use Carbon\Carbon;
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
                ->has('notasPorAprobar')
                ->has('plazoSunat')
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

test('gerente descuenta solo las notas aceptadas del periodo en ventas y facturacion', function () {
    $this->travelTo(Carbon::parse('2026-10-09 12:00:00'));
    $user = createGerenteUserForDashboardTest();
    $sale = Sale::factory()->create(['fecha' => today(), 'total' => 1500, 'comprobante_tipo' => 'factura', 'estado' => 'confirmada']);
    $previousSale = Sale::factory()->create(['fecha' => '2026-09-10', 'total' => 1000, 'comprobante_tipo' => 'boleta', 'estado' => 'confirmada']);
    $cancelled = Sale::factory()->create(['fecha' => today(), 'total' => 100, 'comprobante_tipo' => 'boleta', 'estado' => 'anulada']);
    ElectronicDocument::factory()->create(['sale_id' => $cancelled->id, 'tipo' => 'nota_credito', 'sunat_estado' => 'aceptado', 'importe' => 100, 'fecha_emision' => today()]);
    foreach ([[$sale, 'aceptado', 200, '2026-10-09'], [$sale, 'observado', 100, '2026-10-09'], [$sale, 'rechazado', 500, '2026-10-09'], [$sale, 'pendiente', 500, '2026-10-09'], [$previousSale, 'aceptado', 50, '2026-10-08']] as [$venta, $estado, $importe, $fecha]) {
        ElectronicDocument::factory()->create(['sale_id' => $venta->id, 'tipo' => 'nota_credito', 'sunat_estado' => $estado, 'importe' => $importe, 'fecha_emision' => $fecha]);
    }
    $this->actingAs($user)->get(route('gerente.dashboard', ['current_team' => $user->currentTeam]))
        ->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('metrics.ventasDia', 1200)
        ->where('metrics.ventasMes', 1150)
        ->where('metrics.facturacionMes', 1150)
        ->where('charts.ventasMensuales.5.monto', 1150));
});
