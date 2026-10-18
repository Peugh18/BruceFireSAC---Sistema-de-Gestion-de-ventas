<?php

use App\Enums\TeamRole;
use App\Models\InventoryUnit;
use App\Models\Sede;
use App\Models\ServiceOrder;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('el gerente crea un taller sin convertirlo en almacen ni punto de venta', function () {
    $gerente = User::factory()->create();
    $gerente->assignRole('Gerente');

    $this->actingAs($gerente)->post(route('gerente.sedes.store', $gerente->currentTeam), [
        'nombre' => 'Taller de recargas', 'tipo' => 'taller', 'direccion' => 'Av. Taller 123', 'activo' => true,
    ])->assertSessionHasNoErrors()->assertRedirect();

    $this->assertDatabaseHas('sedes', ['nombre' => 'Taller de recargas', 'tipo' => 'taller', 'almacen_id' => null]);
});

test('el gerente asigna los tecnicos al taller y rechaza una tienda', function (string $rol) {
    $gerente = User::factory()->create();
    $gerente->assignRole('Gerente');
    $taller = Sede::factory()->create(['tipo' => 'taller']);
    $tienda = Sede::factory()->mixta()->create();
    $tecnico = User::factory()->create();
    $tecnico->assignRole($rol);
    $gerente->currentTeam->members()->attach($tecnico, ['role' => TeamRole::Member->value]);
    $url = route('gerente.usuarios.update-sede', ['current_team' => $gerente->currentTeam, 'user' => $tecnico]);

    $this->actingAs($gerente)->patch($url, ['sede_id' => $taller->id])->assertSessionHasNoErrors();
    expect($tecnico->fresh()->sede_id)->toBe($taller->id);
    $this->patch($url, ['sede_id' => $tienda->id])->assertSessionHasErrors('sede_id');
    expect($tecnico->fresh()->sede_id)->toBe($taller->id);
})->with(['TecnicoPlanta', 'TecnicoCampo']);

test('el taller atiende todas las tiendas sin tomar ordenes de otro tecnico', function () {
    $taller = Sede::factory()->create(['tipo' => 'taller']);
    $tienda = Sede::factory()->mixta()->create();
    $otraTienda = Sede::factory()->tienda()->create();
    $tecnico = User::factory()->create(['sede_id' => $taller->id]);
    $tecnico->assignRole('TecnicoPlanta');
    $primera = ServiceOrder::factory()->create(['sede_id' => $tienda->id, 'tecnico_id' => null]);
    $segunda = ServiceOrder::factory()->create(['sede_id' => $otraTienda->id, 'tecnico_id' => null]);
    $ajena = ServiceOrder::factory()->create(['sede_id' => $otraTienda->id, 'tecnico_id' => User::factory()]);

    expect(ServiceOrder::query()->accessibleToTechnician($tecnico)->pluck('id')->all())->toEqualCanonicalizing([$primera->id, $segunda->id]);
    expect($primera->atendiblePor($tecnico))->toBeTrue();
    expect($segunda->atendiblePor($tecnico))->toBeTrue();
    expect($ajena->atendiblePor($tecnico))->toBeFalse();
    expect($otraTienda->almacenEfectivoId())->toBe($otraTienda->almacen_id);
});

test('campo desde taller elige la tienda si hay varias y no ofrece almacenes ni talleres', function () {
    $taller = Sede::factory()->create(['tipo' => 'taller']);
    Sede::factory()->mixta()->create();
    $segunda = Sede::factory()->mixta()->create();
    Sede::factory()->almacen()->create();
    Sede::factory()->mixta()->create(['activo' => false]);
    $campo = User::factory()->create(['sede_id' => $taller->id]);
    $campo->assignRole('TecnicoCampo');

    $this->actingAs($campo)->get(route('tecnico-campo.recojos.create', $campo->currentTeam))
        ->assertOk()->assertInertia(fn (Assert $page) => $page->has('sedes', 2));
    $url = route('tecnico-campo.recojos.iniciar', $campo->currentTeam);
    $datos = ['cliente' => ['razon_social' => 'Cliente de recojo', 'telefono' => '999888777'], 'referencia' => 'Local del cliente', 'cantidad' => 1];
    $this->post($url, $datos)->assertSessionHasErrors('sede_id');
    $this->post($url, [...$datos, 'sede_id' => $taller->id])->assertSessionHasErrors('sede_id');
    $this->post($url, [...$datos, 'sede_id' => $segunda->id])->assertSessionHasNoErrors();
    expect(ServiceOrder::sole()->sede_id)->toBe($segunda->id);
});

test('traslados requiere dos almacenes activos tanto en ruta como en menu', function () {
    $almacen = Sede::factory()->almacen()->create();
    Sede::factory()->mixta()->create(['activo' => false]);
    $user = User::factory()->create(['sede_id' => $almacen->id]);
    $user->assignRole('Almacen');
    $params = ['current_team' => $user->currentTeam];

    $this->actingAs($user)->get(route('almacen.dashboard', $params))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('puedeTrasladar', false));
    $this->get(route('almacen.traslados.index', $params))->assertNotFound();
    $this->post(route('almacen.traslados.store', $params), [])->assertNotFound();

    Sede::factory()->mixta()->create();
    $this->get(route('almacen.traslados.index', $params))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('puedeTrasladar', true));
});

test('un almacen con stock no se convierte en taller', function () {
    $gerente = User::factory()->create();
    $gerente->assignRole('Gerente');
    $almacen = Sede::factory()->almacen()->create();
    InventoryUnit::factory()->create(['sede_almacen_id' => $almacen->id, 'estado' => 'disponible']);

    $this->actingAs($gerente)->put(route('gerente.sedes.update', ['current_team' => $gerente->currentTeam, 'sede' => $almacen]), [
        'nombre' => $almacen->nombre, 'tipo' => 'taller', 'activo' => true,
    ])->assertSessionHasErrors('tipo');
    expect($almacen->fresh()->tipo)->toBe('almacen');
});
