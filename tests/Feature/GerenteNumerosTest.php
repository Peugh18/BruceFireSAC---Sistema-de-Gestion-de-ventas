<?php

use App\Http\Controllers\Gerente\ReportController;
use App\Models\Installment;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\Quote;
use App\Models\Sale;
use App\Models\SalePayment;
use App\Models\Sede;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia as Assert;

function movimiento(array $datos): void
{
    (new InventoryMovement)->forceFill([...$datos, 'user_id' => null, 'observacion' => 'prueba'])->save();
}

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->gerente = User::factory()->create();
    $this->gerente->assignRole('Gerente');
    $this->team = ['current_team' => $this->gerente->currentTeam];
});

test('el inicio del gerente cuenta solo ventas emitidas, cotizaciones ofrecidas y deuda por saldo sin tocar las cuotas', function () {
    Sale::factory()->create(['estado' => 'confirmada', 'fecha' => today(), 'total' => 100]);
    Sale::factory()->create(['estado' => 'borrador', 'fecha' => today(), 'total' => 900]);
    Sale::factory()->create(['estado' => 'anulada', 'fecha' => today(), 'total' => 500]);

    $credito = Sale::factory()->create(['estado' => 'confirmada', 'fecha' => today()->subMonths(2), 'total' => 300]);
    $parcial = Installment::factory()->create(['sale_id' => $credito->id, 'numero_cuota' => 1, 'monto' => 300, 'estado' => 'parcial', 'fecha_vencimiento' => today()->subDays(5)]);
    SalePayment::factory()->create(['sale_id' => $credito->id, 'installment_id' => $parcial->id, 'monto' => 120, 'fecha' => today()->subDays(10)]);
    $vencida = Installment::factory()->create(['sale_id' => $credito->id, 'numero_cuota' => 2, 'monto' => 50, 'estado' => 'pendiente', 'fecha_vencimiento' => today()->subDay()]);
    $deBorrador = Sale::factory()->create(['estado' => 'borrador']);
    Installment::factory()->create(['sale_id' => $deBorrador->id, 'monto' => 999, 'estado' => 'pendiente', 'fecha_vencimiento' => today()->addDays(5)]);

    // Cuenta las ofrecidas que llevan más de 7 días sin respuesta.
    Quote::factory()->create(['estado' => 'emitida', 'fecha' => today()->subDays(10)]);
    Quote::factory()->create(['estado' => 'enviada', 'fecha' => today()->subDays(8)]);
    Quote::factory()->create(['estado' => 'enviada', 'fecha' => today()]);
    Quote::factory()->create(['estado' => 'borrador', 'fecha' => today()->subDays(10)]);

    $this->actingAs($this->gerente)
        ->get(route('gerente.dashboard', $this->team))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('metricas.0.clave', 'ventasNetas')
            ->where('metricas.0.valor', 100)
            ->where('metrics.vencidoPorCobrar', 230)
            ->where('metrics.cotizacionesPendientes', 2));

    // Abrir la pantalla no cambia datos.
    expect($vencida->fresh()->estado)->toBe('pendiente');
});

test('el stock de productos sin serie sale del kardex en productos, inventario e inicio', function () {
    $almacen = Sede::factory()->almacen()->create();
    $otro = Sede::factory()->almacen()->create();
    $guantes = Product::factory()->create(['nombre' => 'Guante nitrilo M', 'serializado' => false, 'stock_minimo' => 5, 'activo' => true]);
    movimiento(['product_id' => $guantes->id, 'sede_id' => $almacen->id, 'tipo' => 'ingreso', 'cantidad' => 20]);
    movimiento(['product_id' => $guantes->id, 'sede_id' => $almacen->id, 'tipo' => 'salida_venta', 'cantidad' => -8]);
    movimiento(['product_id' => $guantes->id, 'sede_id' => $otro->id, 'tipo' => 'ingreso', 'cantidad' => 3]);

    $this->actingAs($this->gerente)
        ->get(route('gerente.productos.index', $this->team))
        ->assertInertia(fn (Assert $page) => $page
            ->where('productos.data.0.stock_disponible', 15));

    $inventario = ReportController::inventarioResumen($otro->id);
    expect($inventario['productos'][0]['stock_disponible'])->toBe(3)
        ->and($inventario['productos'][0]['bajo_minimo'])->toBeTrue();

    $this->actingAs($this->gerente)
        ->get(route('gerente.dashboard', $this->team))
        ->assertInertia(fn (Assert $page) => $page->where('metrics.stockCritico', 0));
});

test('un mes mal escrito en el dashboard no rompe la pantalla ni el PDF', function () {
    $this->actingAs($this->gerente)
        ->get(route('gerente.dashboard', [...$this->team, 'mes' => '2026-13']))
        ->assertOk();
    $this->get(route('gerente.reportes.comercial.pdf', [...$this->team, 'mes' => 'ayer']))
        ->assertOk();
});

test('un gerente no se quita su propio rol', function () {
    $this->actingAs($this->gerente)
        ->patch(route('gerente.usuarios.update-role', [...$this->team, 'user' => $this->gerente]), ['role' => 'Vendedor'])
        ->assertSessionHasErrors('role');

    expect($this->gerente->fresh()->hasRole('Gerente'))->toBeTrue();
});
