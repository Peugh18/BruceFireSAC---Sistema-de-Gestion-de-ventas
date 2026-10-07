<?php

use App\Models\Client;
use App\Models\Sede;
use App\Models\Service;
use App\Models\ServiceOrder;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    // Como en la empresa: la tienda manda sus extintores al taller del almacén.
    $this->almacen = Sede::factory()->mixta()->create();
    $this->tienda = Sede::factory()->tienda()->create(['almacen_id' => $this->almacen->id]);
    $this->otraSede = Sede::factory()->mixta()->create();

    $this->servicio = Service::factory()->create(['nombre' => 'Recarga y mantenimiento']);
    $this->vendedor = User::factory()->create(['sede_id' => $this->tienda->id]);
    $this->vendedor->assignRole('Vendedor');
    $this->tecnico = User::factory()->create(['sede_id' => $this->almacen->id, 'name' => 'Técnico del almacén']);
    $this->tecnico->assignRole('TecnicoPlanta');
    $this->tecnicoAjeno = User::factory()->create(['sede_id' => $this->otraSede->id]);
    $this->tecnicoAjeno->assignRole('TecnicoPlanta');

    $this->orden = ServiceOrder::factory()->create([
        'client_id' => Client::factory()->create()->id,
        'sede_id' => $this->tienda->id,
        'departamento_tecnico' => 'planta',
        'estado' => 'pendiente_recepcion',
    ]);
});

function rutaDeOrden(User $user, string $nombre, ServiceOrder $orden): string
{
    return route($nombre, ['current_team' => $user->currentTeam, 'service_order' => $orden]);
}

test('la vendedora de la tienda ve y asigna al tecnico del almacen', function () {
    $this->actingAs($this->vendedor)
        ->get(rutaDeOrden($this->vendedor, 'vendedor.ordenes-servicio.show', $this->orden))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('tecnicos', 1)
            ->where('tecnicos.0.id', $this->tecnico->id));

    $this->actingAs($this->vendedor)
        ->post(rutaDeOrden($this->vendedor, 'vendedor.ordenes-servicio.asignar-tecnico', $this->orden), ['tecnico_id' => $this->tecnico->id])
        ->assertSessionHasNoErrors();

    expect($this->orden->fresh()->tecnico_id)->toBe($this->tecnico->id);

    $this->actingAs($this->vendedor)
        ->post(rutaDeOrden($this->vendedor, 'vendedor.ordenes-servicio.asignar-tecnico', $this->orden), ['tecnico_id' => $this->tecnicoAjeno->id])
        ->assertNotFound();
});

test('el tecnico del almacen ve la orden de la tienda y un tecnico de otra sede no', function () {
    $this->actingAs($this->tecnico)
        ->get(route('tecnico-planta.recepciones.index', ['current_team' => $this->tecnico->currentTeam]))
        ->assertInertia(fn ($page) => $page->where('orders.data.0.id', $this->orden->id));

    $this->actingAs($this->tecnico)
        ->get(rutaDeOrden($this->tecnico, 'tecnico-planta.recepciones.show', $this->orden))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('asignacion.tecnico', null));

    $this->actingAs($this->tecnicoAjeno)
        ->get(rutaDeOrden($this->tecnicoAjeno, 'tecnico-planta.recepciones.show', $this->orden))
        ->assertNotFound();
});

test('el tecnico toma la orden libre y queda en la bitacora', function () {
    $this->actingAs($this->tecnico)
        ->post(rutaDeOrden($this->tecnico, 'tecnico-planta.ordenes.tomar', $this->orden))
        ->assertRedirect();

    expect($this->orden->fresh()->tecnico_id)->toBe($this->tecnico->id)
        ->and($this->orden->events()->where('payload->accion', 'orden_tomada')->exists())->toBeTrue();
});

test('un segundo tecnico no le quita la orden al que ya la tomo', function () {
    $otro = User::factory()->create(['sede_id' => $this->almacen->id, 'name' => 'Otro técnico']);
    $otro->assignRole('TecnicoPlanta');

    $this->actingAs($this->tecnico)
        ->post(rutaDeOrden($this->tecnico, 'tecnico-planta.ordenes.tomar', $this->orden))
        ->assertRedirect();

    $this->actingAs($otro)
        ->post(rutaDeOrden($otro, 'tecnico-planta.ordenes.tomar', $this->orden))
        ->assertNotFound();

    expect($this->orden->fresh()->tecnico_id)->toBe($this->tecnico->id)
        ->and($this->orden->events()->where('payload->accion', 'orden_tomada')->count())->toBe(1);
});

test('la vendedora edita fecha, prioridad, tecnico e indicaciones y el tecnico lo ve en la bitacora', function () {
    $this->actingAs($this->vendedor)
        ->put(rutaDeOrden($this->vendedor, 'vendedor.ordenes-servicio.update', $this->orden), [
            'service_id' => $this->servicio->id,
            'fecha' => '2026-10-05',
            'prioridad' => 'urgente',
            'departamento_tecnico' => 'planta',
            'tecnico_id' => $this->tecnico->id,
            'observaciones' => 'Cliente pasa a recoger a las 3 pm.',
        ])
        ->assertSessionHasNoErrors();

    $orden = $this->orden->fresh();

    expect($orden->fecha->toDateString())->toBe('2026-10-05')
        ->and($orden->prioridad)->toBe('urgente')
        ->and($orden->tecnico_id)->toBe($this->tecnico->id)
        ->and($orden->observaciones)->toBe('Cliente pasa a recoger a las 3 pm.')
        ->and($orden->events()->where('payload->accion', 'orden_editada')->exists())->toBeTrue();
});

test('ya recibida no cambia de area y entregada no se edita', function () {
    $datos = ['service_id' => $this->servicio->id, 'fecha' => '2026-10-05', 'prioridad' => 'normal', 'departamento_tecnico' => 'campo', 'observaciones' => null];

    $this->orden->update(['estado' => 'en_proceso']);
    $this->actingAs($this->vendedor)
        ->put(rutaDeOrden($this->vendedor, 'vendedor.ordenes-servicio.update', $this->orden), $datos)
        ->assertSessionHasErrors('departamento_tecnico');

    $this->orden->update(['estado' => 'entregado']);
    $this->actingAs($this->vendedor)
        ->put(rutaDeOrden($this->vendedor, 'vendedor.ordenes-servicio.update', $this->orden), [...$datos, 'departamento_tecnico' => 'planta'])
        ->assertSessionHasErrors('estado');
});
