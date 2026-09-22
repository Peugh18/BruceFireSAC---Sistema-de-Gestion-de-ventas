<?php

use App\Models\Quote;
use App\Models\QuoteItem;
use App\Models\Service;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function createGerenteUserForServiceTest(): User
{
    $user = User::factory()->create();
    $user->assignRole('Gerente');

    return $user;
}

test('gerente puede ver listado de servicios con kpis y filtros', function () {
    $user = createGerenteUserForServiceTest();
    Service::factory()->create([
        'codigo' => 'SRV-TEST-01',
        'nombre' => 'Mantenimiento General',
        'precio_venta' => 45.00,
        'activo' => true,
    ]);

    $this->actingAs($user)
        ->get(route('gerente.servicios.index', ['current_team' => $user->currentTeam]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('gerente/servicios/index')
            ->has('servicios.data', 1)
            ->has('kpis.totalServicios')
            ->has('kpis.totalActivos')
        );
});

test('vendedor no puede acceder al crud de servicios de gerente', function () {
    $vendedor = User::factory()->create();
    $vendedor->assignRole('Vendedor');

    $this->actingAs($vendedor)
        ->get(route('gerente.servicios.index', ['current_team' => $vendedor->currentTeam]))
        ->assertForbidden();
});

test('gerente puede crear un nuevo servicio', function () {
    $user = createGerenteUserForServiceTest();

    $data = [
        'codigo' => 'SRV-NEW-01',
        'nombre' => 'Recarga de Gas Carbónico CO2 5lb',
        'descripcion' => 'Servicio técnico especializado con prueba de estanqueidad',
        'unidad_medida' => 'ZZ',
        'precio_venta' => 38.00,
        'aplica_igv' => true,
        'activo' => true,
    ];

    $this->actingAs($user)
        ->post(route('gerente.servicios.store', ['current_team' => $user->currentTeam]), $data)
        ->assertRedirect(route('gerente.servicios.index', ['current_team' => $user->currentTeam]));

    $this->assertDatabaseHas('services', [
        'codigo' => 'SRV-NEW-01',
        'nombre' => 'Recarga de Gas Carbónico CO2 5lb',
        'precio_venta' => 38.00,
    ]);
});

test('gerente puede editar un servicio existente', function () {
    $user = createGerenteUserForServiceTest();
    $service = Service::factory()->create([
        'codigo' => 'SRV-EDIT-01',
        'precio_venta' => 30.00,
    ]);

    $data = [
        'codigo' => 'SRV-EDIT-01',
        'nombre' => 'Servicio Actualizado',
        'unidad_medida' => 'ZZ',
        'precio_venta' => 45.00,
        'aplica_igv' => true,
        'activo' => true,
    ];

    $this->actingAs($user)
        ->put(route('gerente.servicios.update', ['current_team' => $user->currentTeam, 'servicio' => $service]), $data)
        ->assertRedirect(route('gerente.servicios.index', ['current_team' => $user->currentTeam]));

    $this->assertDatabaseHas('services', [
        'id' => $service->id,
        'nombre' => 'Servicio Actualizado',
        'precio_venta' => 45.00,
    ]);
});

test('gerente puede alternar el estado activo del servicio', function () {
    $user = createGerenteUserForServiceTest();
    $service = Service::factory()->create(['activo' => true]);

    $this->actingAs($user)
        ->patch(route('gerente.servicios.toggle-status', ['current_team' => $user->currentTeam, 'servicio' => $service]))
        ->assertRedirect();

    $this->assertDatabaseHas('services', [
        'id' => $service->id,
        'activo' => 0,
    ]);
});

test('regla no negociable: no se puede eliminar un servicio con cotizaciones asociadas', function () {
    $user = createGerenteUserForServiceTest();
    $service = Service::factory()->create();

    $quote = Quote::factory()->create();
    QuoteItem::factory()->create([
        'quote_id' => $quote->id,
        'service_id' => $service->id,
    ]);

    $this->actingAs($user)
        ->delete(route('gerente.servicios.destroy', ['current_team' => $user->currentTeam, 'servicio' => $service]))
        ->assertRedirect()
        ->assertSessionHas('error');

    $this->assertDatabaseHas('services', ['id' => $service->id]);
});

test('se puede eliminar un servicio si no cuenta con historial', function () {
    $user = createGerenteUserForServiceTest();
    $service = Service::factory()->create();

    $this->actingAs($user)
        ->delete(route('gerente.servicios.destroy', ['current_team' => $user->currentTeam, 'servicio' => $service]))
        ->assertRedirect(route('gerente.servicios.index', ['current_team' => $user->currentTeam]))
        ->assertSessionHas('success');

    $this->assertDatabaseMissing('services', ['id' => $service->id]);
});
