<?php

use App\Models\AuditLog;
use App\Models\Sede;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function createGerenteForSedeTest(): User
{
    $user = User::factory()->create();
    $user->assignRole('Gerente');

    return $user;
}

test('gerente ve el listado de sedes con sus almacenes y kpis', function () {
    $gerente = createGerenteForSedeTest();
    $almacen = Sede::factory()->almacen()->create(['nombre' => 'Almacén Central']);
    Sede::factory()->tienda()->create(['nombre' => 'Tienda Norte', 'almacen_id' => $almacen->id]);

    $this->actingAs($gerente)
        ->get(route('gerente.sedes.index', ['current_team' => $gerente->currentTeam]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('gerente/sedes/index')
            ->has('sedes', 2)
            ->has('almacenes', 1)
            ->where('kpis.total', 2)
        );
});

test('un vendedor no puede acceder a sedes del gerente', function () {
    $vendedor = User::factory()->create();
    $vendedor->assignRole('Vendedor');

    $this->actingAs($vendedor)
        ->get(route('gerente.sedes.index', ['current_team' => $vendedor->currentTeam]))
        ->assertForbidden();
});

test('gerente crea una sede almacén y queda auditada', function () {
    $gerente = createGerenteForSedeTest();

    $this->actingAs($gerente)
        ->post(route('gerente.sedes.store', ['current_team' => $gerente->currentTeam]), [
            'nombre' => 'Almacén Trujillo',
            'tipo' => 'almacen',
            'ciudad' => 'Trujillo',
        ])
        ->assertRedirect();

    expect(Sede::where('nombre', 'Almacén Trujillo')->value('tipo'))->toBe('almacen');
    expect(AuditLog::where('action', 'sede.creada')->exists())->toBeTrue();
});

test('una tienda exige un almacén activo del cual sacar stock', function () {
    $gerente = createGerenteForSedeTest();
    $tienda = Sede::factory()->mixta()->create(['activo' => true]);
    $inactivo = Sede::factory()->almacen()->create(['activo' => false]);
    $url = route('gerente.sedes.store', ['current_team' => $gerente->currentTeam]);

    $this->actingAs($gerente)
        ->post($url, ['nombre' => 'Tienda sin almacén', 'tipo' => 'tienda'])
        ->assertSessionHasErrors('almacen_id');

    $this->actingAs($gerente)
        ->post($url, ['nombre' => 'Tienda con almacén inactivo', 'tipo' => 'tienda', 'almacen_id' => $inactivo->id])
        ->assertSessionHasErrors('almacen_id');

    $this->actingAs($gerente)
        ->post($url, ['nombre' => 'Tienda válida', 'tipo' => 'tienda', 'almacen_id' => $tienda->id])
        ->assertSessionHasNoErrors();

    expect(Sede::where('nombre', 'Tienda válida')->value('almacen_id'))->toBe($tienda->id);
});

test('una sede que no es tienda descarta el almacén enviado', function () {
    $gerente = createGerenteForSedeTest();
    $almacen = Sede::factory()->almacen()->create();

    $this->actingAs($gerente)
        ->post(route('gerente.sedes.store', ['current_team' => $gerente->currentTeam]), [
            'nombre' => 'Sede mixta propia',
            'tipo' => 'mixta',
            'almacen_id' => $almacen->id,
        ])
        ->assertSessionHasNoErrors();

    expect(Sede::where('nombre', 'Sede mixta propia')->value('almacen_id'))->toBeNull();
});

test('gerente edita una sede y no puede elegirla como su propio almacén', function () {
    $gerente = createGerenteForSedeTest();
    $almacen = Sede::factory()->almacen()->create();
    $tienda = Sede::factory()->tienda()->create(['almacen_id' => $almacen->id]);
    $url = route('gerente.sedes.update', ['current_team' => $gerente->currentTeam, 'sede' => $tienda]);

    $this->actingAs($gerente)
        ->put($url, ['nombre' => 'Tienda Renombrada', 'tipo' => 'tienda', 'almacen_id' => $tienda->id])
        ->assertSessionHasErrors('almacen_id');

    $this->actingAs($gerente)
        ->put($url, ['nombre' => 'Tienda Renombrada', 'tipo' => 'tienda', 'almacen_id' => $almacen->id])
        ->assertSessionHasNoErrors();

    expect($tienda->fresh()->nombre)->toBe('Tienda Renombrada');
});

test('no se puede repetir el nombre de una sede', function () {
    $gerente = createGerenteForSedeTest();
    Sede::factory()->mixta()->create(['nombre' => 'Sede Lima']);

    $this->actingAs($gerente)
        ->post(route('gerente.sedes.store', ['current_team' => $gerente->currentTeam]), [
            'nombre' => 'Sede Lima',
            'tipo' => 'mixta',
        ])
        ->assertSessionHasErrors('nombre');
});

test('no se desactiva un almacén con tiendas activas ni una sede con trabajadores', function () {
    $gerente = createGerenteForSedeTest();
    $almacen = Sede::factory()->almacen()->create();
    Sede::factory()->tienda()->create(['almacen_id' => $almacen->id]);
    $conTrabajador = Sede::factory()->mixta()->create();
    User::factory()->create(['sede_id' => $conTrabajador->id]);

    $this->actingAs($gerente)
        ->patch(route('gerente.sedes.toggle-status', ['current_team' => $gerente->currentTeam, 'sede' => $almacen]))
        ->assertSessionHas('error');
    $this->actingAs($gerente)
        ->patch(route('gerente.sedes.toggle-status', ['current_team' => $gerente->currentTeam, 'sede' => $conTrabajador]))
        ->assertSessionHas('error');

    expect($almacen->fresh()->activo)->toBeTrue();
    expect($conTrabajador->fresh()->activo)->toBeTrue();
});

test('gerente desactiva y reactiva una sede sin dependencias', function () {
    $gerente = createGerenteForSedeTest();
    $sede = Sede::factory()->mixta()->create();
    $url = route('gerente.sedes.toggle-status', ['current_team' => $gerente->currentTeam, 'sede' => $sede]);

    $this->actingAs($gerente)->patch($url)->assertSessionHas('success');
    expect($sede->fresh()->activo)->toBeFalse();

    $this->actingAs($gerente)->patch($url)->assertSessionHas('success');
    expect($sede->fresh()->activo)->toBeTrue();
});

test('gerente asigna una única sede a un trabajador y puede quitársela', function () {
    $gerente = createGerenteForSedeTest();
    $sede = Sede::factory()->mixta()->create();
    $trabajador = User::factory()->create();
    $trabajador->assignRole('Vendedor');
    $url = route('gerente.usuarios.update-sede', ['current_team' => $gerente->currentTeam, 'user' => $trabajador]);

    $this->actingAs($gerente)->patch($url, ['sede_id' => $sede->id])->assertSessionHasNoErrors();
    expect($trabajador->fresh()->sede_id)->toBe($sede->id);
    expect(AuditLog::where('action', 'usuario.sede_actualizada')->exists())->toBeTrue();

    $this->actingAs($gerente)->patch($url, ['sede_id' => null])->assertSessionHasNoErrors();
    expect($trabajador->fresh()->sede_id)->toBeNull();
});

test('no se asigna a un trabajador una sede inactiva', function () {
    $gerente = createGerenteForSedeTest();
    $inactiva = Sede::factory()->mixta()->create(['activo' => false]);
    $trabajador = User::factory()->create();

    $this->actingAs($gerente)
        ->patch(route('gerente.usuarios.update-sede', ['current_team' => $gerente->currentTeam, 'user' => $trabajador]), ['sede_id' => $inactiva->id])
        ->assertSessionHasErrors('sede_id');

    expect($trabajador->fresh()->sede_id)->toBeNull();
});

test('un vendedor no puede cambiar la sede de un trabajador', function () {
    $vendedor = User::factory()->create();
    $vendedor->assignRole('Vendedor');
    $sede = Sede::factory()->mixta()->create();

    $this->actingAs($vendedor)
        ->patch(route('gerente.usuarios.update-sede', ['current_team' => $vendedor->currentTeam, 'user' => $vendedor]), ['sede_id' => $sede->id])
        ->assertForbidden();
});

test('la lista de usuarios del gerente incluye la sede de cada uno y las sedes activas', function () {
    $gerente = createGerenteForSedeTest();
    Sede::factory()->mixta()->create(['activo' => true]);
    Sede::factory()->mixta()->create(['activo' => false]);

    $this->actingAs($gerente)
        ->get(route('gerente.usuarios.index', ['current_team' => $gerente->currentTeam]))
        ->assertInertia(fn (Assert $page) => $page
            ->component('gerente/usuarios/index')
            ->has('sedes', 1)
            ->has('usuarios.0.sede_id')
        );
});
