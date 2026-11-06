<?php

use App\Enums\TeamRole;
use App\Models\Capacidad;
use App\Models\Equipment;
use App\Models\Evidencia;
use App\Models\Service;
use App\Models\ServiceOrder;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->team = Team::factory()->create();
    foreach (['campo' => 'TecnicoCampo', 'gerente' => 'Gerente'] as $quien => $rol) {
        $this->{$quien} = User::factory()->create(['current_team_id' => $this->team->id]);
        $this->team->members()->attach($this->{$quien}, ['role' => TeamRole::Admin->value]);
        $this->{$quien}->assignRole($rol);
    }
});

/** Orden de recojo con el precio rebajado que dejó campo (S/ 30 frente a S/ 35). */
function ordenConPrecioEspecial(): ServiceOrder
{
    $servicio = Service::factory()->create(['nombre' => 'Recarga PQS ABC 6 kg', 'precio_venta' => 35]);
    $orden = ServiceOrder::factory()->create(['estado' => 'recibido_planta', 'departamento_tecnico' => 'planta']);
    $orden->lines()->create(['service_id' => $servicio->id, 'cantidad' => 2, 'precio_lista' => 35, 'precio_unitario' => 30, 'precio_estado' => 'rebajado']);

    return $orden;
}

test('campo no entrega una orden que nadie ha cobrado', function () {
    $orden = ServiceOrder::factory()->create(['departamento_tecnico' => 'campo', 'estado' => 'listo_entrega']);
    $equipo = Equipment::factory()->create(['client_id' => $orden->client_id]);
    $orden->equipments()->attach($equipo);
    Evidencia::factory()->create(['service_order_id' => $orden->id, 'etapa' => 'entrega', 'tipo' => 'foto']);

    $this->actingAs($this->campo)->post(route('tecnico-campo.entregas.confirm', ['current_team' => $this->team, 'service_order' => $orden]), [
        'receptor_nombre' => 'Rosa Díaz', 'conformidad_aceptada' => true, 'firma' => FIRMA_PNG, 'entregados' => [$equipo->id],
    ])->assertSessionHasErrors(['cobro' => 'Falta cobrar: el vendedor debe cobrar esta orden antes de entregarla.']);

    expect($orden->fresh()->estado)->toBe('listo_entrega');
});

test('el gerente solo ve el precio rebajado que dejo campo, no lo aprueba', function () {
    $orden = ordenConPrecioEspecial();

    $this->actingAs($this->gerente)->get(route('gerente.ordenes.index', ['current_team' => $this->team, 'filtro' => 'precio_distinto']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('gerente/ordenes/index')
            ->where('conPrecioDistinto', 1)
            ->where('ordenes.data.0.codigo', $orden->codigo)
            ->where('ordenes.data.0.precio_distinto', true));

    $this->get(route('gerente.ordenes.show', ['current_team' => $this->team, 'service_order' => $orden]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('gerente/ordenes/show')->where('preciosCampo.0.estado', 'rebajado'));
});

test('sin precio de lista, campo ve lo ultimo que se le dejo a ese cliente', function () {
    $anterior = ServiceOrder::factory()->create(['departamento_tecnico' => 'planta', 'estado' => 'cerrado']);
    $anterior->events()->create(['tipo' => 'otro', 'user_id' => $this->campo->id, 'payload' => [
        'eslabon_custodia' => 'recojo_campo',
        'conteo' => [['tipo_agente' => 'CO2', 'capacidad' => '15 lb', 'cantidad' => 3, 'precio' => 80]],
    ]]);
    $orden = ServiceOrder::factory()->create(['client_id' => $anterior->client_id, 'departamento_tecnico' => 'campo', 'estado' => 'pendiente_recepcion']);

    $this->actingAs($this->campo)->get(route('tecnico-campo.recojos.show', ['current_team' => $this->team, 'service_order' => $orden]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('ultimosPrecios', [['tipo' => 'CO2', 'capacidad' => '15 lb', 'precio' => 80]]));
});

test('campo agrega y quita capacidades como agentes y marcas', function () {
    $this->actingAs($this->campo)->postJson(route('capacidades.store'), ['nombre' => '30LB'])
        ->assertCreated()->assertJsonPath('capacidad.nombre', '30 lb');
    $this->postJson(route('capacidades.store'), ['nombre' => '30 lb'])->assertUnprocessable();
    $this->postJson(route('capacidades.store'), ['nombre' => 'grande'])->assertUnprocessable();
    expect(Capacidad::nombresActivos())->toContain('30 lb');

    $capacidad = Capacidad::query()->where('nombre', '30 lb')->sole();
    $this->deleteJson(route('capacidades.destroy', $capacidad))->assertOk();
    expect($capacidad->fresh()->activo)->toBeFalse();

    // Campo también agrega un agente que no está en la lista.
    $this->postJson(route('agentes.store'), ['nombre' => 'Halotrón', 'tipo_base' => 'agente_limpio'])->assertCreated();
});

test('una capacidad sin precio de lista no frena el recojo y se cobra lo que dejo campo', function () {
    conteoRecojo(); // deja la recarga PQS ABC 6 kg en el catálogo
    $orden = ServiceOrder::factory()->create(['departamento_tecnico' => 'campo', 'estado' => 'pendiente_recepcion']);
    Evidencia::factory()->create(['service_order_id' => $orden->id, 'etapa' => 'recojo', 'tipo' => 'foto']);

    $this->actingAs($this->campo)->post(route('tecnico-campo.recojos.store', ['current_team' => $this->team, 'service_order' => $orden]), [
        'conteo' => [['tipo_agente' => 'PQS ABC', 'capacidad' => '30 lb', 'cantidad' => 1, 'precio' => 90]],
        'contacto_nombre' => 'Rosa Díaz', 'conformidad_cliente' => true, 'firma' => FIRMA_PNG,
    ])->assertSessionHasNoErrors();

    $linea = $orden->lines()->sole();
    expect($linea->precio_lista)->toBeNull()
        ->and((float) $linea->precio_unitario)->toBe(90.0)
        ->and($linea->precio_estado)->toBe('sin_lista')
        // La factura dice la capacidad real: la recarga de 30 lb queda en el catálogo.
        ->and($linea->service->nombre)->toContain('30 lb')
        ->and((float) $linea->service->precio_venta)->toBe(90.0);
});

test('el recojo propone la tienda donde el cliente compra', function () {
    $orden = ServiceOrder::factory()->create();

    $this->actingAs($this->campo)->getJson(route('tecnico-campo.clientes.ficha', ['current_team' => $this->team, 'client' => $orden->client_id]))
        ->assertOk()->assertJsonPath('sede_habitual', $orden->sede_id);
});
