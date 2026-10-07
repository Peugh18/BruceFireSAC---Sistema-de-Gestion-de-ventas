<?php

use App\Actions\Usuarios\CrearTrabajador;
use App\Models\Sede;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('el trabajador creado por el gerente cambia su contraseña antes de usar el sistema', function () {
    $gerente = User::factory()->create();
    $gerente->assignRole('Gerente');

    $trabajador = app(CrearTrabajador::class)->handle($gerente->currentTeam, 'Ana Ruiz', 'ana@example.com', 'Vendedor', Sede::factory()->mixta()->create()->id, 'Inicial123!', $gerente->id);

    expect($trabajador->must_change_password)->toBeTrue();

    $this->actingAs($trabajador)->get(route('profile.edit'))->assertRedirect(route('security.edit'));

    $this->actingAs($trabajador)
        ->from(route('security.edit'))
        ->put(route('user-password.update'), [
            'current_password' => 'Inicial123!',
            'password' => 'Nueva-clave-2026',
            'password_confirmation' => 'Nueva-clave-2026',
        ])
        ->assertSessionHasNoErrors();

    expect($trabajador->refresh()->must_change_password)->toBeFalse();
    $this->actingAs($trabajador)->get(route('profile.edit'))->assertOk();
});

test('el gerente sin verificación en dos pasos solo puede ir a activarla', function () {
    config(['seguridad.exigir_2fa_gerente' => true]);
    $gerente = User::factory()->create();
    $gerente->assignRole('Gerente');

    $this->actingAs($gerente)->get(route('profile.edit'))->assertRedirect(route('security.edit'));

    $gerente->forceFill(['two_factor_secret' => encrypt('secreto'), 'two_factor_confirmed_at' => now()])->save();

    $this->actingAs($gerente->refresh())->get(route('profile.edit'))->assertOk();
});

test('el interruptor apaga la exigencia de dos pasos y no afecta a otros roles', function () {
    config(['seguridad.exigir_2fa_gerente' => false]);
    $gerente = User::factory()->create();
    $gerente->assignRole('Gerente');
    $this->actingAs($gerente)->get(route('profile.edit'))->assertOk();

    config(['seguridad.exigir_2fa_gerente' => true]);
    $vendedor = vendedorUser();
    $this->actingAs($vendedor)->get(route('profile.edit'))->assertOk();
});
