<?php

use App\Enums\TeamRole;
use App\Models\AuditLog;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function createGerenteUserForUserTest(): User
{
    $user = User::factory()->create();
    $user->assignRole('Gerente');

    return $user;
}

test('gerente puede acceder al listado de usuarios y roles', function () {
    $gerente = createGerenteUserForUserTest();

    $this->actingAs($gerente)
        ->get(route('gerente.usuarios.index', ['current_team' => $gerente->currentTeam]))
        ->assertOk();
});

test('almacen recibe 403 al intentar acceder al listado de usuarios de gerente', function () {
    $user = User::factory()->create();
    $user->assignRole('Almacen');

    $this->actingAs($user)
        ->get(route('gerente.usuarios.index', ['current_team' => $user->currentTeam]))
        ->assertForbidden();
});

test('el listado de usuarios incluye a los miembros del team con su rol de negocio', function () {
    $gerente = createGerenteUserForUserTest();

    $vendedor = User::factory()->create();
    $vendedor->assignRole('Vendedor');
    $gerente->currentTeam->members()->attach($vendedor, ['role' => TeamRole::Member->value]);

    $sinRol = User::factory()->create();
    $gerente->currentTeam->members()->attach($sinRol, ['role' => TeamRole::Member->value]);

    $response = $this->actingAs($gerente)
        ->get(route('gerente.usuarios.index', ['current_team' => $gerente->currentTeam]));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->has('usuarios', 3)
        ->where('roles', RolesAndPermissionsSeeder::BUSINESS_ROLES)
        ->has('matrizPermisos.Gerente')
        ->has('matrizPermisos.Vendedor')
        ->has('matrizPermisos.Almacen')
        ->has('matrizPermisos.TecnicoPlanta')
        ->has('matrizPermisos.TecnicoCampo')
    );
});

test('gerente puede asignar uno de los 5 roles fijos a un usuario del team', function () {
    $gerente = createGerenteUserForUserTest();

    $user = User::factory()->create();
    $gerente->currentTeam->members()->attach($user, ['role' => TeamRole::Member->value]);

    $this->actingAs($gerente)
        ->patch(route('gerente.usuarios.update-role', ['current_team' => $gerente->currentTeam, 'user' => $user]), [
            'role' => 'Almacen',
        ])
        ->assertRedirect();

    expect($user->fresh()->hasRole('Almacen'))->toBeTrue()
        ->and(AuditLog::where('action', 'usuario.rol_actualizado')
            ->where('auditable_id', $user->id)
            ->exists())->toBeTrue();
});

test('asignar un rol no reconocido lanza error de validación', function () {
    $gerente = createGerenteUserForUserTest();

    $user = User::factory()->create();
    $gerente->currentTeam->members()->attach($user, ['role' => TeamRole::Member->value]);

    $this->actingAs($gerente)
        ->patch(route('gerente.usuarios.update-role', ['current_team' => $gerente->currentTeam, 'user' => $user]), [
            'role' => 'Administrador',
        ])
        ->assertSessionHasErrors('role');
});

test('cambiar el rol de un usuario reemplaza el rol anterior en vez de acumularlo', function () {
    $gerente = createGerenteUserForUserTest();

    $user = User::factory()->create();
    $user->assignRole('Vendedor');
    $gerente->currentTeam->members()->attach($user, ['role' => TeamRole::Member->value]);

    $this->actingAs($gerente)
        ->patch(route('gerente.usuarios.update-role', ['current_team' => $gerente->currentTeam, 'user' => $user]), [
            'role' => 'TecnicoCampo',
        ]);

    $fresh = $user->fresh();
    expect($fresh->roles->pluck('name')->all())->toBe(['TecnicoCampo']);
});
