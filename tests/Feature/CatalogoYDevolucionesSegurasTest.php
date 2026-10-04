<?php

use App\Actions\Sales\RevertSale;
use App\Models\AuditLog;
use App\Models\CashRegister;
use App\Models\Client;
use App\Models\Installment;
use App\Models\InventoryUnit;
use App\Models\Product;
use App\Models\Quote;
use App\Models\Sale;
use App\Models\SalePayment;
use App\Models\SaleRefund;
use App\Models\Sede;
use App\Models\User;
use App\Models\Vehicle;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\URL;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->sede = Sede::factory()->mixta()->create();
    $this->gerente = User::factory()->create();
    $this->gerente->assignRole('Gerente');
    $this->vendedor = User::factory()->create(['sede_id' => $this->sede->id]);
    $this->vendedor->assignRole('Vendedor');
});

/**
 * @return array<string, mixed>
 */
function ventaNueva(Product $product, array $cambios = []): array
{
    $unit = InventoryUnit::factory()->create([
        'product_id' => $product->id,
        'sede_almacen_id' => test()->sede->id,
        'estado' => 'disponible',
    ]);

    return [
        'client_id' => Client::factory()->create()->id,
        'fecha' => now()->toDateString(),
        'destino' => 'local_cliente',
        'condicion_pago' => 'contado',
        'medio_pago' => 'yape',
        'comprobante_tipo' => 'nota_venta',
        'items' => [['tipo_linea' => 'unidad_nueva', 'numero_serie' => $unit->numero_serie, 'product_id' => $product->id, 'cantidad' => 1, 'precio_unitario' => 100]],
        ...$cambios,
    ];
}

test('no se vende un producto dado de baja ni con la placa de otro cliente', function () {
    $team = ['current_team' => $this->vendedor->currentTeam];
    $deBaja = Product::factory()->create(['activo' => false]);

    $this->actingAs($this->vendedor)
        ->post(route('vendedor.ventas.store', $team), ventaNueva($deBaja))
        ->assertSessionHasErrors('items');

    $datos = ventaNueva(Product::factory()->create());
    $ajeno = Vehicle::create(['client_id' => Client::factory()->create()->id, 'placa' => 'ABC-123']);

    $this->actingAs($this->vendedor)
        ->post(route('vendedor.ventas.store', $team), [...$datos, 'destino' => 'vehiculo', 'vehicle_id' => $ajeno->id])
        ->assertSessionHasErrors('vehicle_id');

    expect(Sale::count())->toBe(0);
});

test('anular una venta a credito devuelve lo cobrado de sus cuotas', function () {
    $sale = Sale::factory()->create(['estado' => 'confirmada', 'condicion_pago' => 'credito', 'total' => 300]);
    $cuota = Installment::factory()->create(['sale_id' => $sale->id, 'monto' => 300, 'estado' => 'parcial']);
    SalePayment::factory()->create(['sale_id' => $sale->id, 'installment_id' => $cuota->id, 'forma_pago' => 'yape', 'monto' => 120]);

    app(RevertSale::class)->handle($sale, 'por prueba');

    expect(SaleRefund::where('sale_id', $sale->id)->sole())
        ->forma_pago->toBe('yape')
        ->and((float) SaleRefund::where('sale_id', $sale->id)->value('monto'))->toBe(120.0);
});

test('lo ya devuelto no se devuelve dos veces al anular', function () {
    $sale = Sale::factory()->create(['estado' => 'confirmada', 'condicion_pago' => 'contado']);
    SalePayment::factory()->create(['sale_id' => $sale->id, 'installment_id' => null, 'forma_pago' => 'yape', 'monto' => 100]);
    SaleRefund::create(['sale_id' => $sale->id, 'forma_pago' => 'yape', 'monto' => 30, 'motivo' => 'Rebaja', 'fecha' => today()]);

    app(RevertSale::class)->handle($sale, 'por prueba');

    expect((float) SaleRefund::where('sale_id', $sale->id)->sum('monto'))->toBe(100.0);
});

test('anular una venta cobrada en efectivo pide la caja abierta', function () {
    $team = ['current_team' => $this->vendedor->currentTeam];
    $sale = Sale::factory()->create([
        'sede_id' => $this->sede->id,
        'vendedor_id' => $this->vendedor->id,
        'estado' => 'confirmada',
        'comprobante_tipo' => 'nota_venta',
        'numero_nota_venta' => 'NV-0500',
    ]);
    SalePayment::factory()->create(['sale_id' => $sale->id, 'installment_id' => null, 'forma_pago' => 'efectivo', 'monto' => 100]);

    $this->actingAs($this->vendedor)
        ->post(route('vendedor.ventas.descartar', [...$team, 'sale' => $sale]))
        ->assertSessionHasErrors('caja');

    expect($sale->fresh()->estado)->toBe('confirmada');

    CashRegister::factory()->create(['vendedor_id' => $this->vendedor->id]);

    $this->actingAs($this->vendedor)
        ->post(route('vendedor.ventas.descartar', [...$team, 'sale' => $sale]))
        ->assertSessionHasNoErrors();

    expect($sale->fresh()->estado)->toBe('anulada');
});

test('un producto con movimientos no cambia de con serie a sin serie y el cambio de precio se audita', function () {
    $team = ['current_team' => $this->gerente->currentTeam];
    $producto = Product::factory()->create(['serializado' => true, 'precio_venta' => 90]);
    InventoryUnit::factory()->create(['product_id' => $producto->id, 'sede_almacen_id' => $this->sede->id]);
    $datos = $producto->only(['codigo', 'nombre', 'unidad_medida']);

    $this->actingAs($this->gerente)
        ->put(route('gerente.productos.update', [...$team, 'producto' => $producto]), [...$datos, 'precio_venta' => 90, 'serializado' => false])
        ->assertSessionHasErrors('serializado');

    $this->actingAs($this->gerente)
        ->put(route('gerente.productos.update', [...$team, 'producto' => $producto]), [...$datos, 'precio_venta' => 110, 'serializado' => true])
        ->assertSessionHasNoErrors();

    $log = AuditLog::where('action', 'producto.actualizado')->sole();

    expect((float) $producto->fresh()->precio_venta)->toBe(110.0)
        ->and((float) $log->old_values['precio_venta'])->toBe(90.0);
});

test('no se desactiva una sede que aun tiene stock', function () {
    $team = ['current_team' => $this->gerente->currentTeam];
    $almacen = Sede::factory()->almacen()->create();
    InventoryUnit::factory()->create(['sede_almacen_id' => $almacen->id, 'estado' => 'disponible']);

    $this->actingAs($this->gerente)
        ->patch(route('gerente.sedes.toggle-status', [...$team, 'sede' => $almacen]))
        ->assertSessionHas('error');

    expect($almacen->fresh()->activo)->toBeTrue();
});

test('el enlace publico de una cotizacion anulada ya no la muestra', function () {
    $cotizacion = Quote::factory()->create(['estado' => 'anulada']);

    $this->get(URL::signedRoute('cotizaciones.publico', ['quote' => $cotizacion]))->assertStatus(410);
});
