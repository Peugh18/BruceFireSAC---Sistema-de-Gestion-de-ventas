<?php

use App\Models\Equipment;
use App\Models\Sede;
use App\Models\ServiceOrder;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->vendedor = vendedorUser();
    $this->sede = Sede::factory()->almacen()->create();
    $this->vendedor->update(['sede_id' => $this->sede->id]);
    $this->campo = User::factory()->create(['sede_id' => $this->sede->id]);
    $this->campo->assignRole('TecnicoCampo');
    $this->planta = User::factory()->create(['sede_id' => $this->sede->id]);
    $this->planta->assignRole('TecnicoPlanta');
    $this->orden = ServiceOrder::factory()->create([
        'sede_id' => $this->sede->id, 'departamento_tecnico' => 'planta',
        'tecnico_id' => $this->planta->id, 'estado' => 'listo_entrega',
    ]);
});

test('relevo de entrega conserva trabajo y permite acceso solo al campo elegido', function () {
    $ruta = ['current_team' => $this->vendedor->currentTeam, 'service_order' => $this->orden];
    $antes = $this->orden->only(['departamento_tecnico', 'estado', 'service_id', 'sale_id']);
    $this->actingAs($this->vendedor)->get(route('vendedor.ordenes-servicio.show', $ruta))
        ->assertInertia(fn ($page) => $page->where('tecnicosEntrega.0.id', $this->campo->id));
    $this->actingAs($this->vendedor)->post(route('vendedor.ordenes-servicio.asignar-entrega', $ruta), ['tecnico_id' => $this->campo->id])
        ->assertRedirect()->assertSessionHasNoErrors();
    expect($this->orden->fresh()->only(array_keys($antes)))->toBe($antes)
        ->and($this->orden->fresh()->tecnico_id)->toBe($this->campo->id);
    $evento = $this->orden->events()->where('payload->accion', 'entrega_asignada')->sole();
    expect($evento->payload['tecnico_anterior_id'])->toBe($this->planta->id)
        ->and($evento->payload['tecnico_id'])->toBe($this->campo->id);
    $this->actingAs($this->campo)->get(route('tecnico-campo.entregas.show', ['current_team' => $this->campo->currentTeam, 'service_order' => $this->orden]))->assertOk();
    $otro = User::factory()->create(['sede_id' => $this->sede->id]);
    $otro->assignRole('TecnicoCampo');
    $this->actingAs($otro)->get(route('tecnico-campo.entregas.show', ['current_team' => $otro->currentTeam, 'service_order' => $this->orden]))->assertNotFound();
});

test('relevo rechaza tecnico ajeno de planta y orden todavia en trabajo', function () {
    $ajeno = User::factory()->create(['sede_id' => Sede::factory()->almacen()->create()->id]);
    $ajeno->assignRole('TecnicoCampo');
    $url = route('vendedor.ordenes-servicio.asignar-entrega', ['current_team' => $this->vendedor->currentTeam, 'service_order' => $this->orden]);
    foreach ([$ajeno->id, $this->planta->id] as $id) {
        $this->actingAs($this->vendedor)->post($url, ['tecnico_id' => $id])->assertSessionHasErrors('tecnico_id');
    }
    $this->orden->update(['estado' => 'en_proceso']);
    $this->actingAs($this->vendedor)->post($url, ['tecnico_id' => $this->campo->id])->assertSessionHasErrors('tecnico_id');
    expect($this->orden->fresh()->tecnico_id)->toBe($this->planta->id)
        ->and($this->orden->events()->count())->toBe(0);
});

test('relevo no permite vendedor de otra sede ni orden entregada', function () {
    $url = route('vendedor.ordenes-servicio.asignar-entrega', ['current_team' => $this->vendedor->currentTeam, 'service_order' => $this->orden]);
    $this->vendedor->update(['sede_id' => Sede::factory()->almacen()->create()->id]);
    $this->actingAs($this->vendedor)->post($url, ['tecnico_id' => $this->campo->id])->assertNotFound();
    $this->vendedor->update(['sede_id' => $this->sede->id]);
    $this->orden->update(['estado' => 'entregado']);
    $this->actingAs($this->vendedor)->post($url, ['tecnico_id' => $this->campo->id])->assertSessionHasErrors('tecnico_id');
});

test('orden finalizada no permite reasignacion ordinaria', function (string $estado) {
    $this->orden->update(['estado' => $estado]);
    $this->actingAs($this->vendedor)->post(route('vendedor.ordenes-servicio.asignar-tecnico', ['current_team' => $this->vendedor->currentTeam, 'service_order' => $this->orden]), ['tecnico_id' => $this->planta->id])
        ->assertSessionHasErrors('tecnico_id');
    expect($this->orden->events()->count())->toBe(0);
})->with(['entregado', 'cerrado', 'anulada']);

test('cerrar entrega previa conserva acta y fechas sin crear segunda entrega', function () {
    $this->orden->update(['estado' => 'entregado', 'tecnico_id' => $this->campo->id]);
    $equipo = Equipment::factory()->create();
    $this->orden->equipments()->attach($equipo->id);
    $antes = $equipo->refresh()->getAttributes();
    $entrega = $this->orden->events()->create(['tipo' => 'trabajo_completado', 'user_id' => $this->campo->id,
        'payload' => ['accion' => 'entrega_final_realizada', 'eslabon_custodia' => 'entrega_campo', 'receptor_nombre' => 'Receptor ficticio']]);
    $snapshot = $entrega->refresh()->getAttributes();
    $url = route('tecnico-campo.entregas.cerrar', ['current_team' => $this->campo->currentTeam, 'service_order' => $this->orden]);
    $this->actingAs($this->campo)->post($url)->assertRedirect()->assertSessionHasNoErrors();
    expect($this->orden->fresh()->estado)->toBe('cerrado')
        ->and($equipo->fresh()->getAttributes())->toBe($antes)
        ->and($entrega->fresh()->getAttributes())->toBe($snapshot)
        ->and($this->orden->events()->where('payload->accion', 'entrega_final_realizada')->count())->toBe(1)
        ->and($this->orden->events()->where('payload->accion', 'orden_cerrada')->count())->toBe(1);
    $this->actingAs($this->campo)->post($url)->assertSessionHasErrors('estado');
    expect($this->orden->events()->count())->toBe(2);
});

test('cierre requiere entrega previa y responsable asignado', function () {
    $this->orden->update(['estado' => 'entregado', 'tecnico_id' => $this->campo->id]);
    $url = route('tecnico-campo.entregas.cerrar', ['current_team' => $this->campo->currentTeam, 'service_order' => $this->orden]);
    $this->actingAs($this->campo)->post($url)->assertSessionHasErrors('estado');
    expect($this->orden->fresh()->estado)->toBe('entregado');
    $this->orden->events()->create(['tipo' => 'otro', 'user_id' => $this->campo->id, 'payload' => ['accion' => 'entrega_final_realizada']]);
    $this->orden->update(['tecnico_id' => $this->planta->id]);
    $this->actingAs($this->campo)->post($url)->assertNotFound();
    expect($this->orden->fresh()->estado)->toBe('entregado');
});

test('lista de entregas incluye certificado listo y sus indicadores', function () {
    $this->orden->update(['estado' => 'listo_certificado', 'tecnico_id' => $this->campo->id]);
    $this->actingAs($this->campo)->get(route('tecnico-campo.entregas.index', ['current_team' => $this->campo->currentTeam]))
        ->assertOk()->assertInertia(fn ($page) => $page
        ->has('entregas.data', 1)->where('entregas.data.0.id', $this->orden->id)
        ->where('stats.listas', 1)->where('stats.total', 1));
});
