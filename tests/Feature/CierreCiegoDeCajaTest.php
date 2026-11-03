<?php

use App\Actions\Cash\OpenCashRegister;
use App\Models\CashRegister;
use App\Models\Sale;
use App\Models\SalePayment;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

/*
| E-011 · Arqueo ciego: el vendedor cuenta por billetes y monedas sin saber
| cuánto debería haber. Si no cuadra, explica sin poder cambiar el conteo; solo
| el Gerente ve la diferencia y puede autorizar un reconteo.
*/

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
    // Esperado: 50 de fondo + 100 cobrados = S/ 150.
    $this->cerrar = fn (array $datos) => $this->actingAs($this->vendedor)->post(
        route('vendedor.caja.cerrar', ['current_team' => $this->vendedor->currentTeam, 'cash_register' => $this->caja]),
        $datos,
    );
    $this->gerente = User::factory()->create(['current_team_id' => $this->vendedor->current_team_id]);
    $this->gerente->assignRole('Gerente');
});

test('el conteo se arma por billetes y monedas y el sistema lo suma', function () {
    ($this->cerrar)(['denominaciones' => ['10000' => 1, '2000' => 2, '1000' => 1]])->assertSessionHasNoErrors();

    $caja = $this->caja->fresh();
    expect($caja->estado)->toBe('cerrado')
        ->and((float) $caja->monto_contado_cierre)->toBe(150.0)
        ->and((float) $caja->diferencia)->toBe(0.0)
        ->and($caja->conteos()->first()->lineas()->pluck('cantidad', 'denominacion')->map(fn ($c) => (int) $c)->all())
        ->toEqual(['10.00' => 1, '20.00' => 2, '100.00' => 1]);
});

test('las monedas con céntimos también se cuentan', function () {
    // Las claves van en céntimos: Laravel lee el punto de «0.10» como un nivel del arreglo.
    ($this->cerrar)(['denominaciones' => ['10000' => 1, '2000' => 2, '500' => 1, '50' => 9, '10' => 5]])
        ->assertSessionHasNoErrors();

    expect((float) $this->caja->fresh()->monto_contado_cierre)->toBe(150.0)
        ->and($this->caja->fresh()->estado)->toBe('cerrado');
});

test('una denominación que no existe o una cantidad negativa se rechazan', function () {
    ($this->cerrar)(['denominaciones' => ['30000' => 1]])->assertSessionHasErrors('denominaciones');
    ($this->cerrar)(['denominaciones' => ['10000' => -1]])->assertSessionHasErrors('denominaciones.10000');

    expect($this->caja->fresh()->estado)->toBe('abierto')
        ->and($this->caja->conteos()->count())->toBe(0);
});

test('si no cuadra, el aviso no dice cuánto falta ni si sobra y el conteo queda registrado', function () {
    $respuesta = ($this->cerrar)(['denominaciones' => ['10000' => 1, '2000' => 2]]);

    $respuesta->assertSessionHasErrors('observacion');
    $mensaje = session('errors')->first('observacion');
    expect($mensaje)->not->toContain('S/')
        ->not->toContain('Faltan')
        ->not->toContain('Sobran')
        ->and($this->caja->fresh()->estado)->toBe('abierto')
        ->and($this->caja->conteos()->whereNull('anulado_at')->count())->toBe(1);
});

test('después de contar, el vendedor no puede cambiar el conteo para cuadrar', function () {
    ($this->cerrar)(['denominaciones' => ['10000' => 1, '2000' => 2]]);
    ($this->cerrar)(['denominaciones' => ['10000' => 1, '5000' => 1], 'observacion' => 'Di vuelto de más'])
        ->assertSessionHasNoErrors();

    $caja = $this->caja->fresh();
    expect($caja->estado)->toBe('cerrado')
        ->and((float) $caja->monto_contado_cierre)->toBe(140.0)
        ->and((float) $caja->diferencia)->toBe(-10.0)
        ->and($caja->observacion)->toBe('Di vuelto de más');
});

test('mientras el conteo espera la explicación no se cobra en efectivo ni se saca dinero', function () {
    ($this->cerrar)(['denominaciones' => ['10000' => 1]]);

    expect(fn () => CashRegister::exigirAbiertaParaEfectivo($this->vendedor->id))
        ->toThrow(ValidationException::class, 'Estás cerrando tu turno');

    $this->post(route('vendedor.caja.salidas.store', ['current_team' => $this->vendedor->currentTeam, 'cash_register' => $this->caja]), [
        'monto' => 5, 'motivo' => 'Taxi',
    ])->assertSessionHasErrors('monto');
    expect($this->caja->salidas()->count())->toBe(0);
});

test('el vendedor no recibe el esperado ni la diferencia en su pantalla', function () {
    ($this->cerrar)(['denominaciones' => ['10000' => 1], 'observacion' => 'Faltó un depósito']);

    $this->get(route('vendedor.caja.index', ['current_team' => $this->vendedor->currentTeam]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('cierres.data', 1)
            ->missing('cierres.data.0.monto_esperado_calculado')
            ->missing('cierres.data.0.diferencia'));
});

test('el gerente autoriza un reconteo y quedan guardados los dos conteos', function () {
    ($this->cerrar)(['denominaciones' => ['10000' => 1, '2000' => 2]]);

    $this->actingAs($this->gerente)
        ->post(route('gerente.cajas.reconteo', ['current_team' => $this->gerente->currentTeam, 'cash_register' => $this->caja]))
        ->assertSessionHasNoErrors();

    ($this->cerrar)(['denominaciones' => ['10000' => 1, '5000' => 1]])->assertSessionHasNoErrors();
    expect($this->caja->fresh()->estado)->toBe('cerrado')
        ->and((float) $this->caja->fresh()->diferencia)->toBe(0.0);

    $this->actingAs($this->gerente)
        ->get(route('gerente.cajas.index', ['current_team' => $this->gerente->currentTeam, 'turno' => $this->caja->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('detalle.conteos', 2)
            ->where('detalle.conteos.0.monto', 140)
            ->where('detalle.conteos.0.reconteo_autorizado_por', $this->gerente->name)
            ->where('detalle.conteos.1.monto', 150)
            ->where('detalle.conteos.1.lineas.0.denominacion', 100)
            ->where('detalle.conteos.1.lineas.0.cantidad', 1));
});

test('el vendedor no puede autorizar su propio reconteo', function () {
    ($this->cerrar)(['denominaciones' => ['10000' => 1]]);

    $this->actingAs($this->vendedor)
        ->post(route('gerente.cajas.reconteo', ['current_team' => $this->vendedor->currentTeam, 'cash_register' => $this->caja]))
        ->assertForbidden();
});

test('el gerente ve en la lista el turno que espera la explicación', function () {
    ($this->cerrar)(['denominaciones' => ['10000' => 1]]);

    $this->actingAs($this->gerente)
        ->get(route('gerente.cajas.index', ['current_team' => $this->gerente->currentTeam]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('cajas.data.0.esperando_explicacion', true)
            ->where('cajas.data.0.monto_contado_cierre', 100)
            ->where('cajas.data.0.monto_esperado_calculado', 150));
});
