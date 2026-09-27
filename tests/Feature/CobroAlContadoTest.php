<?php

use App\Actions\Sales\DescartarVentaSinComprobante;
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

    expect($sale->medio_pago)->toBe('yape')
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

    expect($sale->fresh()->estado)->toBe('anulada')
        ->and(SalePayment::where('sale_id', $sale->id)->exists())->toBeFalse();
});
