<?php

use App\Enums\TeamRole;
use App\Models\AuditLog;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->gerente = User::factory()->create();
    $this->gerente->assignRole('Gerente');
    $this->trabajador = User::factory()->create(['email' => 'vendedor@prueba.pe', 'password' => bcrypt('clave-de-prueba')]);
    $this->trabajador->assignRole('Vendedor');
    $this->gerente->currentTeam->members()->attach($this->trabajador, ['role' => TeamRole::Member->value]);
    $this->ruta = fn (string $nombre, User $user) => route($nombre, ['current_team' => $this->gerente->currentTeam, 'user' => $user]);
});

test('una cuenta desactivada ya no puede iniciar sesión y queda en auditoría', function () {
    $this->actingAs($this->gerente)
        ->patch(($this->ruta)('gerente.usuarios.update-status', $this->trabajador), ['activo' => false])
        ->assertSessionHasNoErrors();

    expect(AuditLog::query()->where('action', 'usuario.desactivado')->exists())->toBeTrue();

    auth()->logout();
    $this->post(route('login.store'), ['email' => 'vendedor@prueba.pe', 'password' => 'clave-de-prueba']);
    $this->assertGuest();
});

test('el gerente no puede desactivarse a sí mismo', function () {
    $this->gerente->currentTeam->members()->syncWithoutDetaching([$this->gerente->id => ['role' => TeamRole::Owner->value]]);

    $this->actingAs($this->gerente)
        ->patch(($this->ruta)('gerente.usuarios.update-status', $this->gerente), ['activo' => false])
        ->assertSessionHasErrors('activo');
});

test('no se cambian datos de alguien que no es de la empresa', function () {
    $ajeno = User::factory()->create();

    $this->actingAs($this->gerente)
        ->patch(($this->ruta)('gerente.usuarios.update', $ajeno), ['name' => 'X', 'email' => 'x@x.pe'])
        ->assertNotFound();
});
