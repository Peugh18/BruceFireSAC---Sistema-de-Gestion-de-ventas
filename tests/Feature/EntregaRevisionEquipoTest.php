<?php

use App\Enums\TeamRole;
use App\Models\Equipment;
use App\Models\ServiceOrder;
use App\Models\Team;
use App\Models\TechnicalChecklist;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia as Assert;

test('entrega muestra la ultima revision de esta orden y no la de otra', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $team = Team::factory()->create();
    $user = User::factory()->create(['current_team_id' => $team->id]);
    $team->members()->attach($user, ['role' => TeamRole::Admin->value]);
    $user->assignRole('TecnicoCampo');
    $order = ServiceOrder::factory()->create(['departamento_tecnico' => 'campo', 'estado' => 'listo_entrega']);
    $equipment = Equipment::factory()->create(['client_id' => $order->client_id]);
    $pending = Equipment::factory()->create(['client_id' => $order->client_id]);
    $order->equipments()->attach([$equipment->id, $pending->id]);
    $base = ['service_order_id' => $order->id, 'equipment_id' => $equipment->id, 'user_id' => $user->id, 'origen' => 'campo', 'items' => []];
    TechnicalChecklist::create([...$base, 'resultado_general' => 'conforme']);
    $latest = TechnicalChecklist::create([...$base, 'resultado_general' => 'observado']);
    $other = ServiceOrder::factory()->create(['client_id' => $order->client_id]);
    TechnicalChecklist::create([...$base, 'service_order_id' => $other->id, 'resultado_general' => 'conforme']);
    TechnicalChecklist::create([...$base, 'service_order_id' => $other->id, 'equipment_id' => $pending->id, 'resultado_general' => 'conforme']);

    $this->actingAs($user)->get(route('tecnico-campo.entregas.show', ['current_team' => $team, 'service_order' => $order]))
        ->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('order.equipments.0.checklists.0.id', $latest->id)
        ->where('order.equipments.0.checklists.0.resultado_general', 'observado')
        ->has('order.equipments.0.checklists', 2)
        ->has('order.equipments.1.checklists', 0));
});
