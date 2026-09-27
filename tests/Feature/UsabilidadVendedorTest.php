<?php

use App\Models\Client;
use App\Models\ElectronicDocument;
use App\Models\Installment;
use App\Models\Sale;
use App\Models\Sede;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('porcentaje ruc activo y habido en clientes se calcula solo sobre clientes con ruc', function () {
    $user = User::factory()->create();
    $user->assignRole('Vendedor');

    // 2 clientes DNI sin datos SUNAT
    Client::factory()->create([
        'tipo_documento' => 'dni',
        'numero_documento' => '10000001',
        'estado_contribuyente' => null,
        'condicion_domicilio' => null,
    ]);
    Client::factory()->create([
        'tipo_documento' => 'dni',
        'numero_documento' => '10000002',
        'estado_contribuyente' => null,
        'condicion_domicilio' => null,
    ]);

    // 1 cliente RUC activo y habido
    Client::factory()->create([
        'tipo_documento' => 'ruc',
        'numero_documento' => '20100000001',
        'estado_contribuyente' => 'ACTIVO',
        'condicion_domicilio' => 'HABIDO',
    ]);

    // 1 cliente RUC no habido
    Client::factory()->create([
        'tipo_documento' => 'ruc',
        'numero_documento' => '20100000002',
        'estado_contribuyente' => 'ACTIVO',
        'condicion_domicilio' => 'NO HABIDO',
    ]);

    $this->actingAs($user)
        ->get(route('vendedor.clientes.index', ['current_team' => $user->currentTeam]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('vendedor/clientes/index')
            ->where('kpis.porcentaje_activo_habido', fn ($v) => (float) $v === 50.0) // 1 de 2 RUCs = 50%, no 1 de 4 clientes = 25%
        );
});

test('el dashboard incluye el telefono del cliente en cobros_pendientes para whatsapp', function () {
    $user = User::factory()->create();
    $user->assignRole('Vendedor');
    $sede = Sede::factory()->almacen()->create();

    $client = Client::factory()->create([
        'razon_social' => 'CLIENTE TEST S.A.C.',
        'whatsapp' => '987654321',
    ]);

    $sale = Sale::factory()->create([
        'vendedor_id' => $user->id,
        'client_id' => $client->id,
        'sede_id' => $sede->id,
    ]);

    $installment = Installment::factory()->create([
        'sale_id' => $sale->id,
        'numero_cuota' => 1,
        'monto' => 350.00,
        'fecha_vencimiento' => now()->subDays(3)->toDateString(),
        'estado' => 'vencido',
    ]);

    $this->actingAs($user)
        ->get(route('vendedor.dashboard', ['current_team' => $user->currentTeam]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('vendedor/dashboard')
            ->has('cobros_pendientes', 1, fn (Assert $item) => $item
                ->where('id', $installment->id)
                ->where('telefono', '987654321')
                ->etc()
            )
        );
});

test('el listado de facturacion incluye sale_id y sunat_mensaje para enlace a venta y estado claro', function () {
    $user = User::factory()->create();
    $user->assignRole('Vendedor');
    $sede = Sede::factory()->almacen()->create();

    $sale = Sale::factory()->create([
        'vendedor_id' => $user->id,
        'sede_id' => $sede->id,
    ]);

    $document = ElectronicDocument::factory()->create([
        'sale_id' => $sale->id,
        'sunat_estado' => 'rechazado',
        'sunat_codigo_respuesta' => '2108',
        'sunat_mensaje' => 'El RUC del receptor no está activo',
    ]);

    $this->actingAs($user)
        ->get(route('vendedor.facturacion.index', ['current_team' => $user->currentTeam]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('vendedor/facturacion/index')
            ->has('documents.data', 1, fn (Assert $item) => $item
                ->where('id', $document->id)
                ->where('sale_id', $sale->id)
                ->where('sunat_mensaje', 'El RUC del receptor no está activo')
                ->etc()
            )
        );
});
