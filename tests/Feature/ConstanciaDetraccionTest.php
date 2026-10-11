<?php

use App\Models\CashRegister;
use App\Models\Installment;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Service;
use App\Models\User;
use App\Services\Caja\DatosDeCaja;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function cuotaConDetraccion(): Installment
{
    $user = User::factory()->create();
    $user->assignRole('Vendedor');
    CashRegister::factory()->create(['vendedor_id' => $user->id]);
    $sale = Sale::factory()->create(['vendedor_id' => $user->id, 'estado' => 'confirmada', 'comprobante_tipo' => 'factura', 'total' => 1180]);
    SaleItem::factory()->forService(Service::factory()->create(['codigo_detraccion' => '022']))->create(['sale_id' => $sale->id]);
    test()->actingAs($user);

    return Installment::factory()->create(['sale_id' => $sale->id, 'monto' => 1180]);
}

test('constancia real acredita saldo sin ingreso ni doble detraccion', function () {
    $cuota = cuotaConDetraccion();
    $ruta = route('vendedor.cobranzas.pagar', ['current_team' => auth()->user()->currentTeam, 'installment' => $cuota]);
    $datos = ['monto' => 141.6, 'forma_pago' => 'deposito', 'es_detraccion' => true, 'numero_operacion' => 'BN-123'];
    $this->post($ruta, $datos)->assertSessionHasNoErrors();
    $pago = $cuota->payments()->sole();
    expect($pago->es_detraccion)->toBeTrue()->and($pago->cash_register_id)->toBeNull()
        ->and($cuota->fresh()->saldo())->toBe(1038.4)
        ->and(CashRegister::abiertaDe($cuota->sale->vendedor_id)->movimientosPorFormaDePago())->toBe([]);
    $this->post($ruta, [...$datos, 'numero_operacion' => 'BN-456'])->assertSessionHasErrors('monto');
    expect($cuota->payments()->count())->toBe(1);
});

test('detraccion exige constancia y medio deposito', function () {
    $cuota = cuotaConDetraccion();
    $ruta = route('vendedor.cobranzas.pagar', ['current_team' => auth()->user()->currentTeam, 'installment' => $cuota]);
    $this->post($ruta, ['monto' => 141.6, 'forma_pago' => 'deposito', 'es_detraccion' => true])->assertSessionHasErrors('numero_operacion');
    $this->post($ruta, ['monto' => 141.6, 'forma_pago' => 'efectivo', 'es_detraccion' => true, 'numero_operacion' => 'BN-MEDIO'])->assertSessionHasErrors('forma_pago');
    expect($cuota->payments()->count())->toBe(0);
});

test('turno mayor de veinticuatro horas avisa y conserva cobros habilitados', function () {
    $cuota = cuotaConDetraccion();
    $turno = CashRegister::abiertaDe($cuota->sale->vendedor_id);
    $turno->update(['fecha_apertura' => now()->subHours(25)]);
    expect(DatosDeCaja::para(auth()->user())['turno_actual']['abierto_mas_de_24_horas'])->toBeTrue();
    $this->post(route('vendedor.cobranzas.pagar', ['current_team' => auth()->user()->currentTeam, 'installment' => $cuota]), ['monto' => 10, 'forma_pago' => 'efectivo'])->assertSessionHasNoErrors();
});

test('constancia al contado acredita pendiente una sola vez y bloquea editar el comprobante', function () {
    $cuota = cuotaConDetraccion();
    $sale = $cuota->sale;
    $sale->update(['condicion_pago' => 'contado']);
    $sale->payments()->create(['monto' => 1038.4, 'forma_pago' => 'transferencia', 'fecha' => today()]);
    $ruta = route('vendedor.ventas.detraccion.store', ['current_team' => auth()->user()->currentTeam, 'sale' => $sale]);

    $this->post($ruta, ['monto' => 141.6, 'numero_operacion' => 'BN-CONTADO'])->assertSessionHasNoErrors();

    expect((float) $sale->payments()->sum('monto'))->toBe(1180.0);
    expect($sale->payments()->where('es_detraccion', true)->sole()->cash_register_id)->toBeNull();
    expect($sale->fresh()->sePuedeEditar())->toBeFalse();
    $this->post($ruta, ['monto' => 141.6, 'numero_operacion' => 'BN-REPETIDA'])->assertSessionHasErrors('monto');
});

test('constancia de otra venta queda oculta al vendedor y venta anulada no se acredita', function () {
    $cuota = cuotaConDetraccion();
    $sale = $cuota->sale;
    $sale->update(['condicion_pago' => 'contado', 'estado' => 'anulada']);
    $ruta = route('vendedor.ventas.detraccion.store', ['current_team' => auth()->user()->currentTeam, 'sale' => $sale]);
    $this->post($ruta, ['monto' => 141.6, 'numero_operacion' => 'BN-ANULADA'])->assertSessionHasErrors('monto');
    $otro = User::factory()->create();
    $otro->assignRole('Vendedor');
    $this->actingAs($otro)->post(route('vendedor.ventas.detraccion.store', ['current_team' => $otro->currentTeam, 'sale' => $sale]), ['monto' => 141.6, 'numero_operacion' => 'BN-AJENA'])->assertNotFound();
    expect($sale->payments()->count())->toBe(0);
});

test('constancia parcial repetida se informa sin duplicar deuda acreditada', function () {
    $cuota = cuotaConDetraccion();
    $ruta = route('vendedor.cobranzas.pagar', ['current_team' => auth()->user()->currentTeam, 'installment' => $cuota]);
    $datos = ['monto' => 40, 'forma_pago' => 'deposito', 'es_detraccion' => true, 'numero_operacion' => 'BN-PARCIAL'];
    $this->post($ruta, $datos)->assertSessionHasNoErrors();

    $this->post($ruta, $datos)->assertSessionHasErrors('numero_operacion');

    expect($cuota->payments()->count())->toBe(1);
    expect($cuota->fresh()->saldo())->toBe(1140.0);
});
