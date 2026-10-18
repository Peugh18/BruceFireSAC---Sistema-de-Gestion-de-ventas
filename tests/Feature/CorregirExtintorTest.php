<?php

use App\Models\Equipment;
use App\Models\ServiceOrder;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->vendedor = vendedorUser();
});

function rutaCorregir(ServiceOrder $orden, Equipment $equipo, User $vendedor): string
{
    return route('vendedor.ordenes-servicio.equipos.update', ['current_team' => $vendedor->currentTeam, 'service_order' => $orden, 'equipment' => $equipo]);
}

test('el vendedor corrige la marca, serie y año de un extintor ya registrado', function () {
    $orden = ServiceOrder::factory()->create(['sede_id' => $this->vendedor->sede_id, 'estado' => 'pendiente_recepcion']);
    $equipo = Equipment::factory()->create(['client_id' => $orden->client_id, 'marca' => 'No legible / Pendiente de verificar']);
    $orden->equipments()->attach($equipo->id);

    $this->actingAs($this->vendedor)
        ->patch(rutaCorregir($orden, $equipo, $this->vendedor), ['marca' => 'Badger', 'serie_fabricante' => ' 568-7188 ', 'anio_fabricacion' => '2022'])
        ->assertSessionHasNoErrors();

    expect($equipo->fresh())
        ->marca->toBe('Badger')
        ->serie_fabricante->toBe('568-7188')
        ->anio_fabricacion->toBe('2022');
});

test('con la orden ya certificada el extintor no se corrige', function () {
    $orden = ServiceOrder::factory()->create(['sede_id' => $this->vendedor->sede_id, 'estado' => 'listo_certificado']);
    $equipo = Equipment::factory()->create(['client_id' => $orden->client_id, 'marca' => 'Badger']);
    $orden->equipments()->attach($equipo->id);

    $this->actingAs($this->vendedor)
        ->patch(rutaCorregir($orden, $equipo, $this->vendedor), ['marca' => 'Amerex'])
        ->assertSessionHasErrors('equipo');

    expect($equipo->fresh()->marca)->toBe('Badger');
});

test('el chat deja fuera los avisos que se repiten por cada extintor', function () {
    $orden = ServiceOrder::factory()->create(['sede_id' => $this->vendedor->sede_id, 'estado' => 'pendiente_recepcion']);
    $orden->events()->create(['tipo' => 'otro', 'payload' => ['accion' => 'alta_tecnica_rapida']]);
    $orden->events()->create(['tipo' => 'otro', 'payload' => ['accion' => 'mensaje', 'mensaje' => 'Llegan mañana', 'origen' => 'planta']]);

    $this->actingAs($this->vendedor)
        ->get(route('vendedor.ordenes-servicio.show', ['current_team' => $this->vendedor->currentTeam, 'service_order' => $orden]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('conversacion.eventos', 1)
            ->where('conversacion.eventos.0.mensaje', 'Llegan mañana'));
});
