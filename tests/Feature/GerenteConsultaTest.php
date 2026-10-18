<?php

use App\Enums\TeamRole;
use App\Models\Client;
use App\Models\Sale;
use App\Models\ServiceOrder;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia;

test('gerente abre fichas de venta orden y cliente sin depender de rutas de vendedor', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $user = User::factory()->create();
    $user->assignRole('Gerente');
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Admin->value]);
    $client = Client::factory()->create(['razon_social' => 'Cliente de auditoría']);
    $sale = Sale::factory()->create(['client_id' => $client->id]);
    $order = ServiceOrder::factory()->create(['client_id' => $client->id]);

    $this->actingAs($user)->get(route('gerente.consulta.venta', [$team, $sale]))
        ->assertInertia(fn (AssertableInertia $page) => $page->component('gerente/consulta/ficha')->where('titulo', 'Venta '.$sale->numero_interno));
    $this->get(route('gerente.consulta.orden', [$team, $order]))
        ->assertInertia(fn (AssertableInertia $page) => $page->component('gerente/consulta/ficha')->where('titulo', 'Orden '.$order->codigo));
    $this->get(route('gerente.consulta.cliente', [$team, $client]))
        ->assertInertia(fn (AssertableInertia $page) => $page->component('gerente/consulta/ficha')->where('titulo', 'Cliente de auditoría'));
});

test('vendedor no accede a las consultas privadas del gerente', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $user = User::factory()->create();
    $user->assignRole('Vendedor');
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Admin->value]);
    $sale = Sale::factory()->create();

    $this->actingAs($user)->get(route('gerente.consulta.venta', [$team, $sale]))->assertForbidden();
});
