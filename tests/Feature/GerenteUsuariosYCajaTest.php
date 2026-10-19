<?php

use App\Enums\TeamRole;
use App\Models\AuditLog;
use App\Models\CashRegister;
use App\Models\Sale;
use App\Models\SalePayment;
use App\Models\SaleRefund;
use App\Models\Sede;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\Request;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Passkeys\Passkey;
use Laravel\Passkeys\Passkeys;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    config(['seguridad.exigir_2fa_gerente' => false]);
});

function gerenteUsuariosYCaja(): User
{
    $gerente = User::factory()->create();
    $gerente->assignRole('Gerente');

    return $gerente;
}

function miembroUsuariosYCaja(User $gerente): User
{
    $miembro = User::factory()->create();
    $gerente->currentTeam->members()->attach($miembro, ['role' => TeamRole::Member->value]);
    $miembro->switchTeam($gerente->currentTeam);

    return $miembro;
}

test('la búsqueda de trabajadores encuentra nombre o correo sin incluir otros equipos', function (string $campo) {
    $gerente = gerenteUsuariosYCaja();
    $miembro = miembroUsuariosYCaja($gerente);
    $miembro->update(['name' => 'Ana Control', 'email' => 'ana.control@example.test']);
    User::factory()->create(['name' => 'Ana Control externa']);
    $buscar = $campo === 'name' ? 'Ana Control' : 'ana.control@example.test';

    $this->actingAs($gerente)->get(route('gerente.usuarios.index', ['current_team' => $gerente->currentTeam, 'buscar' => $buscar]))
        ->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('gerente/usuarios/index')->has('usuarios', 1)
        ->where('usuarios.0.id', $miembro->id)->where('usuarios.0.activo', true)
        ->where('filters.buscar', $buscar));
})->with(['nombre' => 'name', 'correo' => 'email']);

test('editar los datos conserva la cuenta y audita antes y después', function () {
    $gerente = gerenteUsuariosYCaja();
    $miembro = miembroUsuariosYCaja($gerente);
    $antes = $miembro->only(['name', 'email']);
    $password = $miembro->password;

    $this->actingAs($gerente)->patch(route('gerente.usuarios.update', ['current_team' => $gerente->currentTeam, 'user' => $miembro]), [
        'name' => 'Nombre corregido', 'email' => 'CORREGIDO@example.test',
    ])->assertRedirect()->assertSessionHasNoErrors();

    $miembro->refresh();
    expect($miembro->name)->toBe('Nombre corregido')->and($miembro->email)->toBe('corregido@example.test')
        ->and($miembro->email_verified_at)->toBeNull()->and($miembro->password)->toBe($password);
    $evento = AuditLog::query()->where('action', 'usuario.actualizado')->where('auditable_id', $miembro->id)->sole();
    expect($evento->old_values)->toBe($antes)->and($evento->new_values)->toBe(['name' => 'Nombre corregido', 'email' => 'corregido@example.test']);
});

test('editar rechaza correos duplicados y datos vacíos', function () {
    $gerente = gerenteUsuariosYCaja();
    $miembro = miembroUsuariosYCaja($gerente);
    $ruta = route('gerente.usuarios.update', ['current_team' => $gerente->currentTeam, 'user' => $miembro]);

    $this->actingAs($gerente)->patch($ruta, ['name' => '', 'email' => ''])->assertSessionHasErrors(['name', 'email']);
    $this->patch($ruta, ['name' => 'Intento', 'email' => $gerente->email])->assertSessionHasErrors('email');
    expect($miembro->fresh()->email)->toBe($miembro->email);
});

test('no se pueden cambiar datos ni accesos de un trabajador ajeno', function (string $ruta, array $datos) {
    $gerente = gerenteUsuariosYCaja();
    $ajeno = User::factory()->create();

    $this->actingAs($gerente)->patch(route($ruta, ['current_team' => $gerente->currentTeam, 'user' => $ajeno]), $datos)->assertNotFound();
})->with([
    'datos' => ['gerente.usuarios.update', ['name' => 'Otro', 'email' => 'otro@example.test']],
    'estado' => ['gerente.usuarios.update-status', ['activo' => false]],
    'rol' => ['gerente.usuarios.update-role', ['role' => 'Almacen']],
    'sede' => ['gerente.usuarios.update-sede', ['sede_id' => null]],
]);

test('solo el gerente administra el estado y los datos de trabajadores', function (string $ruta, array $datos) {
    $vendedor = User::factory()->create();
    $vendedor->assignRole('Vendedor');

    $this->actingAs($vendedor)->patch(route($ruta, ['current_team' => $vendedor->currentTeam, 'user' => $vendedor]), $datos)->assertForbidden();
})->with([
    'datos' => ['gerente.usuarios.update', ['name' => 'Otro', 'email' => 'otro@example.test']],
    'estado' => ['gerente.usuarios.update-status', ['activo' => false]],
]);

test('desactivar y reactivar conserva el historial y registra los dos cambios', function () {
    $gerente = gerenteUsuariosYCaja();
    $miembro = miembroUsuariosYCaja($gerente);
    $ruta = route('gerente.usuarios.update-status', ['current_team' => $gerente->currentTeam, 'user' => $miembro]);
    $password = $miembro->password;
    $token = $miembro->getRememberToken();
    expect((bool) $miembro->fresh()->getAttribute('activo'))->toBeTrue();

    $this->actingAs($gerente)->patch($ruta, ['activo' => false])->assertRedirect()->assertSessionHasNoErrors();
    expect((bool) $miembro->fresh()->getAttribute('activo'))->toBeFalse()
        ->and($miembro->fresh()->getRememberToken())->not->toBe($token);
    $evento = AuditLog::query()->where('action', 'usuario.desactivado')->where('auditable_id', $miembro->id)->sole();
    expect($evento->old_values)->toBe(['activo' => true])->and($evento->new_values)->toBe(['activo' => false]);

    $this->patch($ruta, ['activo' => true])->assertRedirect()->assertSessionHasNoErrors();
    $evento = AuditLog::query()->where('action', 'usuario.activado')->where('auditable_id', $miembro->id)->sole();
    expect($evento->old_values)->toBe(['activo' => false])->and($evento->new_values)->toBe(['activo' => true])
        ->and($miembro->fresh()->password)->toBe($password)
        ->and($gerente->currentTeam->members()->whereKey($miembro->id)->exists())->toBeTrue();
});

test('el gerente no se desactiva ni se quita su propio rol', function () {
    $gerente = gerenteUsuariosYCaja();
    $params = ['current_team' => $gerente->currentTeam, 'user' => $gerente];
    $this->actingAs($gerente)->patch(route('gerente.usuarios.update-status', $params), ['activo' => false])->assertSessionHasErrors('activo');
    $this->patch(route('gerente.usuarios.update-role', $params), ['role' => 'Vendedor'])->assertSessionHasErrors('role');
    expect((bool) $gerente->fresh()->getAttribute('activo'))->toBeTrue()->and($gerente->fresh()->hasRole('Gerente'))->toBeTrue();
});

test('rol y sede guardan el valor anterior y el nuevo en auditoría', function () {
    $gerente = gerenteUsuariosYCaja();
    $miembro = miembroUsuariosYCaja($gerente);
    $miembro->assignRole('Almacen');
    $sede = Sede::factory()->taller()->create();
    $params = ['current_team' => $gerente->currentTeam, 'user' => $miembro];

    $this->actingAs($gerente)->patch(route('gerente.usuarios.update-role', $params), ['role' => 'TecnicoCampo'])->assertSessionHasNoErrors();
    $this->patch(route('gerente.usuarios.update-sede', $params), ['sede_id' => $sede->id])->assertSessionHasNoErrors();
    $rol = AuditLog::query()->where('action', 'usuario.rol_actualizado')->where('auditable_id', $miembro->id)->sole();
    $sedeEvento = AuditLog::query()->where('action', 'usuario.sede_actualizada')->where('auditable_id', $miembro->id)->sole();
    expect($rol->old_values)->toBe(['role' => 'Almacen'])->and($rol->new_values)->toBe(['role' => 'TecnicoCampo'])
        ->and($sedeEvento->old_values)->toBe(['sede_id' => null])->and($sedeEvento->new_values)->toBe(['sede_id' => $sede->id]);
});

test('una cuenta desactivada no inicia sesión con contraseña', function () {
    $user = User::factory()->create();
    $user->forceFill(['activo' => false])->save();
    $this->post(route('login'), ['email' => $user->email, 'password' => 'password'])->assertSessionHasErrors('email');
    $this->assertGuest();
});

test('las cuentas activas conservan el inicio de sesión', function () {
    $user = User::factory()->create();
    $this->post(route('login'), ['email' => $user->email, 'password' => 'password'])->assertRedirect();
    $this->assertAuthenticatedAs($user);
});

test('la suspensión corta sesiones existentes incluso en la pantalla de seguridad', function () {
    $user = User::factory()->create();
    $user->forceFill(['activo' => false])->save();
    $this->actingAs($user)->get(route('security.edit'))->assertRedirect(route('login'))->assertSessionHasErrors('email');
    $this->assertGuest();
});

test('una cuenta suspendida no completa un desafío 2FA pendiente', function () {
    $user = User::factory()->withTwoFactor()->create();
    $user->forceFill(['activo' => false])->save();
    $codes = $user->recoveryCodes();
    $this->withSession(['login.id' => $user->id, 'login.remember' => false])
        ->post(route('two-factor.login.store'), ['recovery_code' => $codes[0]])
        ->assertRedirect(route('login'))->assertSessionMissing('login.id');
    $this->assertGuest();
    expect($user->fresh()->recoveryCodes())->toBe($codes);
});

test('la autorización de passkeys respeta el estado de la cuenta', function (bool $activo) {
    $user = User::factory()->create();
    $user->forceFill(['activo' => $activo])->save();
    $passkey = (new Passkey)->setRelation('user', $user);
    expect(Passkeys::allowsLogin(Request::create('/passkeys/login', 'POST'), $passkey))->toBe($activo);
})->with(['activa' => true, 'desactivada' => false]);

test('el detalle de caja reúne cobros netos salidas y los importes históricos de cierre', function () {
    $gerente = gerenteUsuariosYCaja();
    $caja = CashRegister::factory()->cerrado()->create([
        'monto_apertura' => 100, 'monto_esperado_calculado' => 999, 'monto_contado_cierre' => 989,
        'observacion' => 'Falta vuelto',
    ]);
    $venta = Sale::factory()->create(['vendedor_id' => $caja->vendedor_id]);
    SalePayment::factory()->create(['sale_id' => $venta->id, 'cash_register_id' => $caja->id, 'forma_pago' => 'efectivo', 'monto' => 80, 'es_detraccion' => false]);
    SalePayment::factory()->create(['sale_id' => $venta->id, 'cash_register_id' => $caja->id, 'forma_pago' => 'yape', 'monto' => 25, 'es_detraccion' => false]);
    SalePayment::factory()->create(['sale_id' => $venta->id, 'cash_register_id' => $caja->id, 'forma_pago' => 'deposito', 'monto' => 12, 'es_detraccion' => true]);
    $anulado = SalePayment::factory()->create(['sale_id' => $venta->id, 'cash_register_id' => $caja->id, 'anulacion_cash_register_id' => $caja->id, 'forma_pago' => 'efectivo', 'monto' => 7, 'es_detraccion' => false]);
    $anulado->delete();
    SaleRefund::create(['sale_id' => $venta->id, 'cash_register_id' => $caja->id, 'forma_pago' => 'efectivo', 'monto' => 5, 'motivo' => 'Devolución', 'fecha' => today()]);
    $caja->salidas()->create(['user_id' => $caja->vendedor_id, 'monto' => 10, 'motivo' => 'Movilidad']);
    $ruta = route('gerente.cajas.index', ['current_team' => $gerente->currentTeam, 'turno' => $caja->id]);

    $this->actingAs($gerente)->get($ruta)->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('gerente/cajas/index')->where('detalle.id', $caja->id)
        ->where('detalle.monto_apertura', 100)->where('detalle.cobros_por_forma_pago.efectivo', 75)
        ->where('detalle.cobros_por_forma_pago.yape', 25)->missing('detalle.cobros_por_forma_pago.deposito')
        ->has('detalle.salidas', 1)->where('detalle.salidas.0.motivo', 'Movilidad')->where('detalle.total_salidas', 10)
        ->where('detalle.monto_esperado_calculado', 999)->where('detalle.monto_contado_cierre', 989)
        ->where('detalle.diferencia', -10)->where('detalle.observacion', 'Falta vuelto'));
});

test('los cierres antiguos sin motivo explican el faltante sin inventar una observación', function () {
    $gerente = gerenteUsuariosYCaja();
    $caja = CashRegister::factory()->cerrado()->create(['monto_esperado_calculado' => 100, 'monto_contado_cierre' => 95, 'observacion' => null]);
    $this->actingAs($gerente)->get(route('gerente.cajas.index', ['current_team' => $gerente->currentTeam, 'turno' => $caja->id]))
        ->assertInertia(fn (Assert $page) => $page->where('detalle.observacion', 'Sin explicación (cierre anterior a la regla)'));
    expect($caja->fresh()->observacion)->toBeNull();
});

test('el detalle de caja rechaza turnos abiertos y no concede acceso al vendedor', function () {
    $gerente = gerenteUsuariosYCaja();
    $abierta = CashRegister::factory()->create();
    $ruta = route('gerente.cajas.index', ['current_team' => $gerente->currentTeam, 'turno' => $abierta->id]);
    $this->actingAs($gerente)->get($ruta)->assertNotFound();
    $vendedor = miembroUsuariosYCaja($gerente);
    $vendedor->assignRole('Vendedor');
    $this->actingAs($vendedor)->get($ruta)->assertForbidden();
});
