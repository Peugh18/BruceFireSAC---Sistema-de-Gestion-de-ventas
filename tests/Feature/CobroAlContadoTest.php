<?php

use App\Actions\Cash\CloseCashRegister;
use App\Actions\Sales\DescartarVentaSinComprobante;
use App\Models\CashRegister;
use App\Models\Client;
use App\Models\InventoryUnit;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SalePayment;
use App\Models\Sede;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function vendedorDeCaja(): User
{
    $user = User::factory()->create();
    $user->assignRole('Vendedor');
    CashRegister::factory()->create(['vendedor_id' => $user->id]);

    return $user;
}

/**
 * Registra y confirma una nota de venta de S/ 100 desde el formulario.
 */
function venderAlContado(User $vendedor, array $pago): Sale
{
    $sede = Sede::factory()->almacen()->create();
    $product = Product::factory()->create(['precio_venta' => 100]);
    $unit = InventoryUnit::factory()->create(['product_id' => $product->id, 'sede_almacen_id' => $sede->id, 'estado' => 'disponible']);
    $team = ['current_team' => $vendedor->currentTeam];

    test()->actingAs($vendedor)
        ->post(route('vendedor.ventas.store', $team), [
            'client_id' => Client::factory()->create()->id,
            'sede_id' => $sede->id,
            'fecha' => now()->toDateString(),
            'destino' => 'local_cliente',
            'comprobante_tipo' => 'nota_venta',
            'items' => [['tipo_linea' => 'unidad_nueva', 'numero_serie' => $unit->numero_serie, 'product_id' => $product->id, 'cantidad' => 1, 'precio_unitario' => 100]],
            ...$pago,
        ])
        ->assertSessionHasNoErrors();

    $sale = Sale::latest('id')->firstOrFail();

    test()->actingAs($vendedor)->post(route('vendedor.ventas.confirmar', [...$team, 'sale' => $sale]));

    return $sale->fresh();
}

test('al emitir una venta al contado el cobro queda registrado con su medio de pago', function () {
    $sale = venderAlContado(vendedorDeCaja(), ['condicion_pago' => 'contado', 'medio_pago' => 'yape', 'numero_operacion' => '123456']);

    $pago = SalePayment::where('sale_id', $sale->id)->sole();

    expect($sale->medio_pago)->toBeNull()
        ->and($sale->numero_operacion)->toBeNull()
        ->and($sale->medioPagoTexto())->toBe('Yape')
        ->and($pago->forma_pago)->toBe('yape')
        ->and((float) $pago->monto)->toBe((float) $sale->total)
        ->and($pago->numero_operacion)->toBe('123456')
        ->and($pago->installment_id)->toBeNull();
});

test('si no se elige medio al contado se asume efectivo y a credito no se cobra al emitir', function () {
    $vendedor = vendedorDeCaja();

    $contado = venderAlContado($vendedor, ['condicion_pago' => 'contado']);
    expect(SalePayment::where('sale_id', $contado->id)->value('forma_pago'))->toBe('efectivo');

    $credito = venderAlContado($vendedor, [
        'condicion_pago' => 'credito',
        'medio_pago' => 'yape',
        'cuotas' => [['fecha_vencimiento' => now()->addDays(30)->toDateString(), 'monto' => 100]],
    ]);
    expect($credito->medio_pago)->toBeNull()
        ->and(SalePayment::where('sale_id', $credito->id)->exists())->toBeFalse();
});

test('un medio de pago inventado se rechaza', function () {
    $vendedor = vendedorDeCaja();

    $this->actingAs($vendedor)
        ->post(route('vendedor.ventas.store', ['current_team' => $vendedor->currentTeam]), [
            'client_id' => Client::factory()->create()->id,
            'fecha' => now()->toDateString(),
            'destino' => 'local_cliente',
            'condicion_pago' => 'contado',
            'medio_pago' => 'trueque',
            'comprobante_tipo' => 'nota_venta',
            'items' => [],
        ])
        ->assertSessionHasErrors('medio_pago');
});

test('al anular la venta el cobro al contado sale de la caja', function () {
    $sale = venderAlContado(vendedorDeCaja(), ['condicion_pago' => 'contado', 'medio_pago' => 'efectivo']);
    expect(SalePayment::where('sale_id', $sale->id)->exists())->toBeTrue();

    app(DescartarVentaSinComprobante::class)->handle($sale, 'por prueba');

    // El cobro queda como se hizo y la devolución se registra hoy aparte:
    // el efectivo sale de la caja del turno en que se devuelve.
    expect($sale->fresh()->estado)->toBe('anulada')
        ->and((float) SalePayment::where('sale_id', $sale->id)->sum('monto'))->toBe(100.0)
        ->and((float) $sale->refunds()->sole()->monto)->toBe(100.0);
});

test('editar una venta cobrada en un turno anterior deja ese cobro y registra hoy solo la diferencia', function () {
    // A mediodía: "ayer más una hora" no puede caer en el día de hoy.
    $this->travelTo(today()->setTime(12, 0));
    $vendedor = vendedorDeCaja();
    $sale = venderAlContado($vendedor, ['condicion_pago' => 'contado', 'medio_pago' => 'efectivo']);
    $original = SalePayment::where('sale_id', $sale->id)->sole();

    // Ese turno ya se cerró ayer; hoy abrió uno nuevo.
    CashRegister::where('vendedor_id', $vendedor->id)->update(['estado' => 'cerrado', 'fecha_apertura' => now()->subDay(), 'fecha_cierre' => now()->subDay()->addHours(8)]);
    $original->forceFill(['created_at' => now()->subDay()->addHour(), 'fecha' => today()->subDay()])->saveQuietly();
    $hoy = CashRegister::factory()->create(['vendedor_id' => $vendedor->id, 'fecha_apertura' => now()->subMinutes(10), 'monto_apertura' => 50]);

    $item = $sale->load('items.inventoryUnit')->items->first();
    $this->actingAs($vendedor)
        ->put(route('vendedor.ventas.update', ['current_team' => $vendedor->currentTeam, 'sale' => $sale]), [
            'client_id' => $sale->client_id,
            'sede_id' => $sale->sede_id,
            'fecha' => $sale->fecha->toDateString(),
            'destino' => 'local_cliente',
            'condicion_pago' => 'contado',
            'medio_pago' => 'efectivo',
            'comprobante_tipo' => 'nota_venta',
            'items' => [['tipo_linea' => 'unidad_nueva', 'numero_serie' => $item->inventoryUnit->numero_serie, 'product_id' => $item->product_id, 'cantidad' => 1, 'precio_unitario' => 120]],
        ])
        ->assertSessionHasNoErrors();

    $pagos = SalePayment::where('sale_id', $sale->id)->orderBy('id')->get();

    expect($pagos)->toHaveCount(2)
        ->and($pagos[0]->id)->toBe($original->id)
        ->and((float) $pagos[0]->monto)->toBe(100.0)
        ->and($pagos[0]->created_at->isToday())->toBeFalse()
        ->and((float) $pagos[1]->monto)->toBe(20.0)
        ->and($pagos[1]->forma_pago)->toBe('efectivo');

    // El arqueo de hoy espera el fondo más solo los 20 que entraron hoy.
    app(CloseCashRegister::class)->handle($hoy, 70, null);
    expect((float) $hoy->fresh()->monto_esperado_calculado)->toBe(70.0);
});
