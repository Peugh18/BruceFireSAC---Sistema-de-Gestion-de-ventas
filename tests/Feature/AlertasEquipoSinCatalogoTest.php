<?php

use App\Models\Client;
use App\Models\Equipment;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('el vendedor puede consultar alertas de equipos recibidos sin producto de catalogo', function (string $estado, string $ruta) {
    $user = vendedorUser();
    $client = Client::factory()->create(['tipo_documento' => 'RUC']);
    $equipment = Equipment::factory()->create([
        'client_id' => $client->id,
        'product_id' => null,
        'estado' => $estado,
        'proxima_fecha_atencion' => today()->subDay(),
        'proxima_prueba_hidrostatica' => null,
    ]);

    $response = $this->actingAs($user)
        ->get(route($ruta, ['current_team' => $user->currentTeam]))
        ->assertOk();

    if ($ruta === 'vendedor.alertas.index') {
        $props = $response->viewData('page')['props'];
        $alerta = collect($props['alerts']['vencidas'])->firstWhere('equipment_id', $equipment->id);
        $empresa = collect($props['empresas'])->firstWhere('client_id', $client->id);

        expect($alerta['equipo'])->toBe('Extintor sin producto de catálogo')
            ->and(collect($empresa['equipos'])->firstWhere('equipment_id', $equipment->id)['equipo'])
            ->toBe('Extintor sin producto de catálogo');
    }
})->with(['activo', 'descargado'])->with(['vendedor.dashboard', 'vendedor.alertas.index']);
