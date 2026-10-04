<?php

use App\Models\AuditLog;
use App\Models\CashRegister;
use App\Models\Installment;
use App\Models\Sale;
use App\Models\SalePayment;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

test('limita el cobro al saldo y permite anularlo con motivo y auditoria', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $vendedor = User::factory()->create();
    $vendedor->assignRole('Vendedor');
    CashRegister::factory()->create(['vendedor_id' => $vendedor->id]);
    $sale = Sale::factory()->create(['estado' => 'confirmada', 'vendedor_id' => $vendedor->id]);
    $installment = Installment::factory()->create([
        'sale_id' => $sale->id,
        'monto' => 300,
        'estado' => 'pendiente',
    ]);
    SalePayment::factory()->create([
        'sale_id' => $sale->id,
        'installment_id' => $installment->id,
        'monto' => 100,
        'fecha' => today(),
    ]);

    $route = route('vendedor.cobranzas.pagar', [
        'current_team' => $vendedor->currentTeam,
        'installment' => $installment,
    ]);

    $this->actingAs($vendedor)->post($route, [
        'monto' => 200.01,
        'forma_pago' => 'efectivo',
    ])->assertSessionHasErrors(['monto' => 'El monto no puede superar el saldo de S/ 200.00.']);

    $this->actingAs($vendedor)->post($route, [
        'monto' => 200,
        'forma_pago' => 'efectivo',
    ])->assertSessionHasNoErrors();

    $payment = SalePayment::query()->where('installment_id', $installment->id)->latest('id')->firstOrFail();
    expect($installment->refresh()->estado)->toBe('pagado');

    $this->actingAs($vendedor)->delete(route('vendedor.cobranzas.pagos.anular', [
        'current_team' => $vendedor->currentTeam,
        'payment' => $payment,
    ]), ['motivo' => 'Se registró dos veces'])->assertSessionHasNoErrors();

    // El cobro anulado no se borra: queda tachado con motivo y quién lo anuló.
    expect($payment->fresh()->trashed())->toBeTrue()
        ->and($payment->fresh()->anulado_motivo)->toBe('Se registró dos veces')
        ->and($payment->fresh()->anulado_por)->toBe($vendedor->id)
        ->and($installment->refresh()->estado)->toBe('parcial')
        ->and(AuditLog::where('action', 'cobro.anulado')->where('auditable_id', $payment->id)->exists())->toBeTrue();
});

test('solo gerencia puede anular un cobro de un dia anterior', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $vendedor = User::factory()->create();
    $vendedor->assignRole('Vendedor');
    $gerente = User::factory()->create();
    $gerente->assignRole('Gerente');
    $sale = Sale::factory()->create(['estado' => 'confirmada', 'vendedor_id' => $vendedor->id]);
    $installment = Installment::factory()->create(['sale_id' => $sale->id, 'estado' => 'pagado']);
    $payment = SalePayment::factory()->create([
        'sale_id' => $sale->id,
        'installment_id' => $installment->id,
        'monto' => $installment->monto,
        'fecha' => today()->subDay(),
    ]);

    $this->actingAs($vendedor)->delete(route('vendedor.cobranzas.pagos.anular', [
        'current_team' => $vendedor->currentTeam,
        'payment' => $payment,
    ]), ['motivo' => 'Cobro equivocado'])->assertForbidden();

    $this->actingAs($gerente)->delete(route('gerente.cobranzas.pagos.anular', [
        'current_team' => $gerente->currentTeam,
        'payment' => $payment,
    ]), ['motivo' => 'Cobro equivocado'])->assertSessionHasNoErrors();

    expect($payment->fresh()->trashed())->toBeTrue()
        ->and(SalePayment::query()->whereKey($payment->id)->exists())->toBeFalse();
});
