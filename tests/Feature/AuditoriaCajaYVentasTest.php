<?php

use App\Actions\Billing\IssueCreditNote;
use App\Actions\Sales\ConfirmSale;
use App\Models\CashRegister;
use App\Models\Client;
use App\Models\CompanySetting;
use App\Models\ElectronicDocument;
use App\Models\Installment;
use App\Models\InventoryUnit;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SalePayment;
use App\Models\SaleRefund;
use App\Models\Sede;
use App\Models\Service;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->vendedor = User::factory()->create();
    $this->vendedor->assignRole('Vendedor');
});

/**
 * Factura aceptada por SUNAT de una venta cobrada al contado en efectivo.
 */
function facturaCobradaEnEfectivo(User $vendedor): ElectronicDocument
{
    $sale = Sale::factory()->create([
        'vendedor_id' => $vendedor->id,
        'estado' => 'confirmada',
        'condicion_pago' => 'contado',
        'comprobante_tipo' => 'factura',
        'subtotal' => 100,
        'igv' => 18,
        'total' => 118,
    ]);
    SalePayment::factory()->create(['sale_id' => $sale->id, 'installment_id' => null, 'forma_pago' => 'efectivo', 'monto' => 118]);

    return ElectronicDocument::factory()->create(['sale_id' => $sale->id, 'tipo' => 'factura', 'serie' => 'F001', 'sunat_estado' => 'aceptado']);
}

test('una nota de credito que anula una venta cobrada en efectivo pide la caja abierta', function () {
    $factura = facturaCobradaEnEfectivo($this->vendedor);

    // Un descuento parcial no devuelve todo el efectivo: no necesita caja.
    app(IssueCreditNote::class)->handle($factura, '04', 'Descuento', 10);

    expect(fn () => app(IssueCreditNote::class)->handle($factura, '01', 'Anulación', 108))
        ->toThrow(ValidationException::class, 'abre la caja');

    CashRegister::factory()->create(['vendedor_id' => $this->vendedor->id]);

    $nota = app(IssueCreditNote::class)->handle($factura, '01', 'Anulación', 108);

    expect($nota->exists)->toBeTrue();
});

test('editar en otro turno una venta en efectivo a un monto menor pide la caja abierta para devolver', function () {
    $sede = Sede::factory()->almacen()->create();
    $product = Product::factory()->create(['precio_venta' => 100]);
    $unit = InventoryUnit::factory()->create(['product_id' => $product->id, 'sede_almacen_id' => $sede->id, 'estado' => 'disponible']);
    $team = ['current_team' => $this->vendedor->currentTeam];
    $caja = CashRegister::factory()->create(['vendedor_id' => $this->vendedor->id]);
    $venta = [
        'client_id' => Client::factory()->create()->id,
        'sede_id' => $sede->id,
        'fecha' => now()->toDateString(),
        'destino' => 'local_cliente',
        'condicion_pago' => 'contado',
        'medio_pago' => 'efectivo',
        'comprobante_tipo' => 'nota_venta',
        'items' => [['tipo_linea' => 'unidad_nueva', 'numero_serie' => $unit->numero_serie, 'product_id' => $product->id, 'cantidad' => 1, 'precio_unitario' => 100]],
    ];

    $this->actingAs($this->vendedor)->post(route('vendedor.ventas.store', $team), $venta)->assertSessionHasNoErrors();
    $sale = Sale::latest('id')->firstOrFail();
    $this->actingAs($this->vendedor)->post(route('vendedor.ventas.confirmar', [...$team, 'sale' => $sale]))->assertSessionHasNoErrors();

    // El turno del cobro ya se cerró y hoy no hay otro abierto.
    $caja->update(['estado' => 'cerrado', 'fecha_cierre' => now()]);
    SalePayment::where('sale_id', $sale->id)->update(['created_at' => now()->subDay()]);

    $this->actingAs($this->vendedor)
        ->put(route('vendedor.ventas.update', [...$team, 'sale' => $sale]), [
            ...$venta,
            'items' => [[...$venta['items'][0], 'precio_unitario' => 80]],
        ])
        ->assertSessionHasErrors('medio_pago');

    expect((float) $sale->fresh()->total)->toBe(100.0)
        ->and(SaleRefund::where('sale_id', $sale->id)->exists())->toBeFalse();
});

test('un borrador a credito emitido otro dia corre sus cuotas con la fecha de la venta', function () {
    $sale = Sale::factory()->create([
        'vendedor_id' => $this->vendedor->id,
        'estado' => 'borrador',
        'condicion_pago' => 'credito',
        'comprobante_tipo' => 'nota_venta',
        'fecha' => today()->subDays(10),
        'total' => 300,
    ]);
    $cuota = Installment::factory()->create(['sale_id' => $sale->id, 'monto' => 300, 'estado' => 'pendiente', 'fecha_vencimiento' => today()->addDays(5)]);

    app(ConfirmSale::class)->handle($sale);

    expect($sale->fresh()->fecha->toDateString())->toBe(today()->toDateString())
        ->and($cuota->fresh()->fecha_vencimiento->toDateString())->toBe(today()->addDays(15)->toDateString());
});

test('con detraccion pagada por deposito el cobro del cliente y la detraccion quedan separados', function () {
    config(['billing.sunat.cert_path' => base_path('tests/Fixtures/certificates/test-certificate.pem')]);
    CompanySetting::current()->update(['cuenta_detraccion' => '00-123-456789']);
    $sale = Sale::factory()->create([
        'client_id' => Client::factory()->create(['tipo_documento' => 'ruc', 'numero_documento' => '20'.fake()->unique()->numerify('#########')])->id,
        'comprobante_tipo' => 'factura',
        'condicion_pago' => 'contado',
        'estado' => 'borrador',
        'medio_pago' => 'deposito',
        'numero_operacion' => 'OP-555',
        'subtotal' => 1000,
        'igv' => 180,
        'total' => 1180,
    ]);
    SaleItem::factory()->forService(Service::factory()->create(['precio_venta' => 1000]))->create([
        'sale_id' => $sale->id, 'cantidad' => 1, 'precio_unitario' => 1000, 'descuento' => 0, 'subtotal' => 1000,
    ]);

    app(ConfirmSale::class)->handle($sale);

    $pagos = SalePayment::where('sale_id', $sale->id)->orderBy('id')->get();

    expect($pagos)->toHaveCount(2)
        ->and((float) $pagos[0]->monto)->toBe(1038.4)
        ->and($pagos[0]->numero_operacion)->toBe('OP-555')
        ->and((float) $pagos[1]->monto)->toBe(141.6)
        ->and($pagos[1]->numero_operacion)->toBe('Detracción (Banco de la Nación)');
});
