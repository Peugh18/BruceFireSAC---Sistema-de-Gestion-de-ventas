<?php

use App\Models\Client;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function createGerenteUserForReportTest(): User
{
    $user = User::factory()->create();
    $user->assignRole('Gerente');

    return $user;
}

test('gerente puede acceder al centro de reportes y ver el reporte comercial', function () {
    $gerente = createGerenteUserForReportTest();
    $client = Client::factory()->create();

    Sale::factory()->create([
        'client_id' => $client->id,
        'vendedor_id' => $gerente->id,
        'fecha' => today(),
        'total' => 800.00,
        'estado' => 'confirmada',
    ]);

    $this->actingAs($gerente)
        ->get(route('gerente.reportes.index', ['current_team' => $gerente->currentTeam, 'tipo' => 'comercial']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('gerente/reportes/index')
            ->where('tipo', 'comercial')
            ->has('reporteComercial')
            ->where('reporteComercial.totalVentas', 800)
            ->where('reporteComercial.cantidadVentas', 1)
        );
});

test('gerente puede consultar el reporte de inventario', function () {
    $gerente = createGerenteUserForReportTest();

    Product::factory()->create([
        'codigo' => 'PROD-REP-01',
        'nombre' => 'Producto Reporte Test',
        'precio_venta' => 100.00,
        'stock_minimo' => 5,
        'activo' => true,
    ]);

    $this->actingAs($gerente)
        ->get(route('gerente.reportes.index', ['current_team' => $gerente->currentTeam, 'tipo' => 'inventario']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('gerente/reportes/index')
            ->where('tipo', 'inventario')
            ->has('reporteInventario')
            ->has('reporteInventario.productos', 1)
        );
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
