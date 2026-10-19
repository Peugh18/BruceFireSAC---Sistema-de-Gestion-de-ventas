<?php

use App\Actions\Cash\CloseCashRegister;
use App\Actions\Cash\OpenCashRegister;
use App\Models\AuditLog;
use App\Models\CashRegister;
use App\Models\Sale;
use App\Models\SalePayment;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->vendedor = User::factory()->create();
    $this->vendedor->assignRole('Vendedor');
    $this->caja = app(OpenCashRegister::class)->handle($this->vendedor, null, 50);
    $venta = Sale::factory()->create(['vendedor_id' => $this->vendedor->id, 'estado' => 'confirmada']);
    SalePayment::factory()->create([
        'sale_id' => $venta->id,
        'installment_id' => null,
        'forma_pago' => 'efectivo',
        'monto' => 100,
        'cash_register_id' => $this->caja->id,
    ]);
    $this->ruta = fn (string $nombre) => route($nombre, ['current_team' => $this->vendedor->currentTeam, 'cash_register' => $this->caja]);
});

test('una salida de dinero con motivo baja el efectivo esperado al cerrar', function () {
    $this->actingAs($this->vendedor)
        ->post(($this->ruta)('vendedor.caja.salidas.store'), ['monto' => 30, 'motivo' => 'Compra de útiles de limpieza'])
        ->assertSessionHasNoErrors();

    $cerrada = app(CloseCashRegister::class)->handle($this->caja, 120);

    expect((float) $cerrada->monto_esperado_calculado)->toBe(120.0)
        ->and((float) $cerrada->diferencia)->toBe(0.0)
        ->and(AuditLog::query()->where('action', 'caja.salida')->exists())->toBeTrue();
});

test('no se puede sacar más efectivo del que hay ni sin motivo', function () {
    $this->actingAs($this->vendedor)
        ->post(($this->ruta)('vendedor.caja.salidas.store'), ['monto' => 151, 'motivo' => 'Pago a proveedor'])
        ->assertSessionHasErrors('monto');

    $this->post(($this->ruta)('vendedor.caja.salidas.store'), ['monto' => 10, 'motivo' => ''])
        ->assertSessionHasErrors('motivo');

    expect($this->caja->salidas()->count())->toBe(0);
});

test('si falta o sobra dinero el cierre exige explicarlo y queda en auditoría', function () {
    expect(fn () => app(CloseCashRegister::class)->handle($this->caja, 140))
        ->toThrow(ValidationException::class);
    expect($this->caja->fresh()->estado)->toBe('abierto');

    $cerrada = app(CloseCashRegister::class)->handle($this->caja, 140, 'Di vuelto de más a un cliente');

    expect($cerrada->estado)->toBe('cerrado')
        ->and((float) $cerrada->diferencia)->toBe(-10.0)
        ->and(AuditLog::query()->where('action', 'caja.cierre_con_diferencia')->exists())->toBeTrue();
});

test('otro vendedor no puede sacar dinero de una caja ajena', function () {
    $otro = User::factory()->create(['current_team_id' => $this->vendedor->current_team_id]);
    $otro->assignRole('Vendedor');

    $this->actingAs($otro)
        ->post(($this->ruta)('vendedor.caja.salidas.store'), ['monto' => 5, 'motivo' => 'Taxi'])
        ->assertForbidden();

    expect(CashRegister::query()->findOrFail($this->caja->id)->salidas()->count())->toBe(0);
});

test('la campana del gerente cuenta los cierres de la semana que no cuadraron', function () {
    app(CloseCashRegister::class)->handle($this->caja, 140, 'Faltó vuelto');
    $gerente = User::factory()->create(['current_team_id' => $this->vendedor->current_team_id]);
    $gerente->assignRole('Gerente');

    $this->actingAs($gerente)
        ->get(route('gerente.cajas.index', ['current_team' => $this->vendedor->currentTeam]))
        ->assertInertia(fn ($page) => $page->where('cierresConDiferencia', 1));
});
