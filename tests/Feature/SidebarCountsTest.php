<?php

use App\Models\Client;
use App\Models\Deficiency;
use App\Models\Equipment;
use App\Models\Quote;
use App\Models\ServiceOrder;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

if (! function_exists('vendedorUser')) {
    function vendedorUser(): User
    {
        $user = User::factory()->create();
        $user->assignRole('Vendedor');

        return $user;
    }
}

test('el sidebar del vendedor recibe conteos reales, no placeholders fijos', function () {
    $user = vendedorUser();

    $clientesActivosAntes = Client::where('activo', true)->count();

    Client::factory()->count(3)->create(['activo' => true]);
    $inactiveClient = Client::factory()->create(['activo' => false]);
    $sharedClient = $inactiveClient;

    Quote::factory()->create(['client_id' => $sharedClient->id, 'vendedor_id' => $user->id, 'estado' => 'enviada']);
    Quote::factory()->create(['client_id' => $sharedClient->id, 'vendedor_id' => $user->id, 'estado' => 'borrador']);
    Quote::factory()->create(['client_id' => $sharedClient->id, 'vendedor_id' => User::factory(), 'estado' => 'enviada']);

    Equipment::factory()->create(['client_id' => $sharedClient->id, 'proxima_fecha_atencion' => now()->addDays(2)]);
    Equipment::factory()->create(['client_id' => $sharedClient->id, 'proxima_fecha_atencion' => now()->addDays(30)]);

    $serviceOrder = ServiceOrder::factory()->create(['client_id' => $sharedClient->id]);
    $equipment = Equipment::factory()->create(['client_id' => $sharedClient->id]);
    Deficiency::factory()->create(['service_order_id' => $serviceOrder->id, 'equipment_id' => $equipment->id, 'estado' => 'esperando_autorizacion']);
    Deficiency::factory()->create(['service_order_id' => $serviceOrder->id, 'equipment_id' => $equipment->id, 'estado' => 'resuelta']);

    $response = $this->actingAs($user)
        ->get(route('vendedor.dashboard', ['current_team' => $user->currentTeam]))
        ->assertOk();

    $counts = $response->viewData('page')['props']['sidebarCounts'];

    expect($counts['clientes'])->toBe($clientesActivosAntes + 3)
        ->and($counts['cotizaciones'])->toBe(1)
        ->and($counts['alertas'])->toBe(1)
        ->and($counts['deficiencias'])->toBe(1);
});

test('sidebarCounts es null para roles que no son vendedor', function () {
    $gerente = User::factory()->create();
    $gerente->assignRole('Gerente');

    $response = $this->actingAs($gerente)
        ->get(route('gerente.configuracion.empresa.edit', ['current_team' => $gerente->currentTeam]))
        ->assertOk();

    expect($response->viewData('page')['props']['sidebarCounts'])->toBeNull();
});
