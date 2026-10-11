<?php

use App\Enums\TeamRole;
use App\Models\Equipment;
use App\Models\Sede;
use App\Models\Service;
use App\Models\ServiceOrder;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->team = Team::factory()->create();
    $this->sede = Sede::factory()->create();
    $this->user = User::factory()->create(['current_team_id' => $this->team->id, 'sede_id' => $this->sede->id]);
    $this->team->members()->attach($this->user, ['role' => TeamRole::Admin->value]);
    $this->user->assignRole('TecnicoCampo');
});

test('colas de campo separan actividad sin mezclar departamento con servicio', function (string $cola, string $prop, string $nombre) {
    $esperada = ServiceOrder::factory()->create(['service_id' => Service::factory()->create(['nombre' => $nombre]), 'sede_id' => $this->sede->id, 'departamento_tecnico' => 'campo', 'tecnico_id' => $this->user->id]);
    foreach (['Inspección técnica', 'Instalación de extintores', 'Mantenimiento en sitio', 'Recarga PQS 6 kg'] as $otro) {
        if ($otro !== $nombre) {
            ServiceOrder::factory()->create(['service_id' => Service::factory()->create(['nombre' => $otro]), 'sede_id' => $this->sede->id, 'departamento_tecnico' => 'campo']);
        }
    }
    ServiceOrder::factory()->create(['service_id' => $esperada->service_id, 'sede_id' => Sede::factory(), 'departamento_tecnico' => 'campo']);
    ServiceOrder::factory()->create(['service_id' => $esperada->service_id, 'sede_id' => $this->sede->id, 'departamento_tecnico' => 'campo', 'tecnico_id' => User::factory()]);
    $this->actingAs($this->user)->get(route("tecnico-campo.{$cola}.index", ['current_team' => $this->team]))
        ->assertOk()->assertInertia(fn (Assert $page) => $page->has("{$prop}.data", 1)->where("{$prop}.data.0.id", $esperada->id));
})->with([
    ['inspecciones', 'inspecciones', 'Inspección técnica'],
    ['instalaciones', 'instalaciones', 'Instalación de extintores'],
    ['mantenimientos', 'mantenimientos', 'Mantenimiento en sitio'],
    ['recojos', 'recojos', 'Recarga PQS 6 kg'],
]);

test('ficha BF EQ conserva solo historia de orden atendible y rechaza equipo ajeno', function () {
    $equipo = Equipment::factory()->create();
    $propia = ServiceOrder::factory()->create(['sede_id' => $this->sede->id, 'tecnico_id' => $this->user->id, 'departamento_tecnico' => 'campo']);
    $propia->equipments()->attach($equipo);
    $otra = ServiceOrder::factory()->create(['sede_id' => $this->sede->id, 'tecnico_id' => User::factory(), 'departamento_tecnico' => 'campo']);
    $otra->equipments()->attach($equipo);
    $ajeno = Equipment::factory()->create();
    $otra->equipments()->attach($ajeno);
    $this->actingAs($this->user)->get(route('equipos.show', ['current_team' => $this->team, 'equipment' => $equipo]))
        ->assertOk()->assertInertia(fn (Assert $page) => $page->component('equipos/show')->where('equipment.numero_serie', $equipo->numero_serie)->has('historia', 1)->where('historia.0.id', $propia->id));
    $this->get(route('equipos.show', ['current_team' => $this->team, 'equipment' => $ajeno]))->assertNotFound();
});

test('una orden entregada, cerrada o anulada se marca como finalizada y deja de ofrecerse para tomar', function (string $estado, bool $finalizada) {
    $orden = ServiceOrder::factory()->create(['estado' => $estado, 'sede_id' => $this->sede->id, 'departamento_tecnico' => 'campo']);

    expect($orden->asignacionPara($this->user)['finalizada'])->toBe($finalizada);
})->with([['pendiente_recepcion', false], ['listo_entrega', false], ['entregado', true], ['cerrado', true], ['anulada', true]]);

test('el técnico que registra el trabajo toma la orden si nadie la tenía y no pisa a otro responsable', function () {
    $libre = ServiceOrder::factory()->create(['sede_id' => $this->sede->id, 'departamento_tecnico' => 'campo', 'tecnico_id' => null]);
    $libre->tomarSiLibre($this->user);

    $ajena = ServiceOrder::factory()->create(['sede_id' => $this->sede->id, 'departamento_tecnico' => 'campo', 'tecnico_id' => User::factory()]);
    $responsable = $ajena->tecnico_id;
    $ajena->tomarSiLibre($this->user);

    expect($libre->fresh()->tecnico_id)->toBe($this->user->id)
        ->and($libre->events()->where('payload->accion', 'orden_tomada')->count())->toBe(1)
        ->and($ajena->fresh()->tecnico_id)->toBe($responsable);
});
