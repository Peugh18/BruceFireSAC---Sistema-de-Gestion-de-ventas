<?php

use App\Models\Equipment;
use App\Models\Sede;
use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('el marco muestra la sede asignada al vendedor y no la de la sesion', function () {
    $sede = Sede::factory()->mixta()->create();
    $otraSede = Sede::factory()->mixta()->create();
    $user = vendedorUser();
    $user->update(['sede_id' => $sede->id]);

    $this->actingAs($user)->withSession(['current_sede_id' => $otraSede->id])
        ->get(route('vendedor.dashboard', ['current_team' => $user->currentTeam]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('currentSede.id', $sede->id)
            ->where('currentSede.nombre', $sede->nombre));
});

test('el inicio muestra equipos reales de esta semana y excluye los posteriores', function () {
    $this->freezeTime();
    $user = vendedorUser();
    $equipo = Equipment::factory()->create([
        'proxima_fecha_atencion' => today()->addDays(3),
        'proxima_prueba_hidrostatica' => null,
    ]);
    Equipment::factory()->create([
        'proxima_fecha_atencion' => today()->addDays(20),
        'proxima_prueba_hidrostatica' => null,
    ]);

    $this->actingAs($user)
        ->get(route('vendedor.dashboard', ['current_team' => $user->currentTeam]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('por_vencer_semana', 1)
            ->where('por_vencer_semana.0.equipment_id', $equipo->id)
            ->where('por_vencer_semana.0.cliente', $equipo->client->razon_social));
});

test('el inicio devuelve una lista vacia cuando no hay equipos por vencer', function () {
    $user = vendedorUser();

    $this->actingAs($user)
        ->get(route('vendedor.dashboard', ['current_team' => $user->currentTeam]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('por_vencer_semana', 0)
            ->where('currentSede', null));
});
