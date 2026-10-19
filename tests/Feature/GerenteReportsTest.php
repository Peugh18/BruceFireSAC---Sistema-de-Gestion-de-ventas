<?php

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function createGerenteUserForReportTest(): User
{
    $user = User::factory()->create();
    $user->assignRole('Gerente');

    return $user;
}

test('la ruta vieja de reportes lleva al dashboard con el mes elegido', function () {
    $gerente = createGerenteUserForReportTest();
    $equipo = $gerente->currentTeam;

    $this->actingAs($gerente)
        ->get(route('gerente.reportes.index', ['current_team' => $equipo, 'mes' => '2026-09']))
        ->assertRedirect(route('gerente.dashboard', ['current_team' => $equipo, 'mes' => '2026-09']));
});

test('vendedor no puede acceder al centro de reportes de gerente', function () {
    $vendedor = User::factory()->create();
    $vendedor->assignRole('Vendedor');

    $this->actingAs($vendedor)
        ->get(route('gerente.reportes.index', ['current_team' => $vendedor->currentTeam]))
        ->assertForbidden();
});

test('gerente puede exportar reporte comercial a PDF', function () {
    $gerente = createGerenteUserForReportTest();

    $response = $this->actingAs($gerente)
        ->get(route('gerente.reportes.comercial.pdf', ['current_team' => $gerente->currentTeam]));

    $response->assertOk();
    $response->assertHeader('content-type', 'application/pdf');
});

test('gerente puede exportar reporte de inventario a PDF', function () {
    $gerente = createGerenteUserForReportTest();

    $response = $this->actingAs($gerente)
        ->get(route('gerente.reportes.inventario.pdf', ['current_team' => $gerente->currentTeam]));

    $response->assertOk();
    $response->assertHeader('content-type', 'application/pdf');
});
