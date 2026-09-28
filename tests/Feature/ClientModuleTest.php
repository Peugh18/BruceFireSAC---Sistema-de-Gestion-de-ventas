<?php

use App\Models\Client;
use App\Models\Sale;
use App\Models\User;
use App\Services\Sunat\RucLookupService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('creates a valid client for vendedor users', function () {
    $user = vendedorUser();

    $response = $this
        ->actingAs($user)
        ->post(route('vendedor.clientes.store', ['current_team' => $user->currentTeam]), [
            'tipo_documento' => 'ruc',
            'numero_documento' => '20601234567',
            'razon_social' => 'BRUCE FIRE CLIENTE S.A.C.',
            'nombre_comercial' => 'Cliente Peru',
            'email' => 'cliente@example.com',
            'direccion_fiscal' => 'Av. America Norte 123, Trujillo',
            'ubigeo' => '130101',
            'estado_contribuyente' => 'ACTIVO',
            'condicion_domicilio' => 'HABIDO',
            'activo' => true,
        ]);

    $client = Client::where('numero_documento', '20601234567')->firstOrFail();

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('vendedor.clientes.show', [
            'current_team' => $user->currentTeam,
            'client' => $client,
        ]));

    $this->assertDatabaseHas('clients', [
        'codigo_interno' => 'CLI-'.str_pad((string) $client->id, 4, '0', STR_PAD_LEFT),
        'tipo_documento' => 'ruc',
        'numero_documento' => '20601234567',
        'razon_social' => 'BRUCE FIRE CLIENTE S.A.C.',
    ]);
});

test('rejects a ruc with an invalid length', function () {
    $user = vendedorUser();

    $response = $this
        ->actingAs($user)
        ->from(route('vendedor.clientes.index', ['current_team' => $user->currentTeam]))
        ->post(route('vendedor.clientes.store', ['current_team' => $user->currentTeam]), [
            'tipo_documento' => 'ruc',
            'numero_documento' => '12345678',
            'razon_social' => 'RUC CORTO S.A.C.',
        ]);

    $response
        ->assertSessionHasErrors('numero_documento')
        ->assertRedirect(route('vendedor.clientes.index', ['current_team' => $user->currentTeam]));

    $this->assertDatabaseMissing('clients', [
        'numero_documento' => '12345678',
    ]);
});

test('ruc lookup returns local client data without sending an http request', function () {
    $client = Client::factory()->create([
        'numero_documento' => '20609999991',
        'razon_social' => 'EXTINTORES DEL NORTE S.A.C.',
        'direccion_fiscal' => 'Jr. Pizarro 456, Trujillo',
        'estado_contribuyente' => 'ACTIVO',
        'condicion_domicilio' => 'HABIDO',
    ]);

    Http::preventStrayRequests();
    Http::fake([
        'https://dniruc.apisperu.com/*' => Http::response([], 500),
    ]);

    $result = app(RucLookupService::class)->lookup($client->numero_documento);

    expect($result)->toBe([
        'razon_social' => 'EXTINTORES DEL NORTE S.A.C.',
        'direccion' => 'Jr. Pizarro 456, Trujillo',
        'estado_contribuyente' => 'ACTIVO',
        'condicion_domicilio' => 'HABIDO',
        'ubigeo' => '130101',
    ]);

    Http::assertNothingSent();
});

test('ruc lookup sends an http request when the document is not local', function () {
    config(['services.apisperu.token' => 'test-token']);

    Http::preventStrayRequests();
    Http::fake([
        'https://dniruc.apisperu.com/api/v1/ruc/*' => Http::response([
            'razonSocial' => 'SERVICIOS INDUSTRIALES TRUJILLO S.A.C.',
            'direccion' => 'Av. Industrial 789',
            'estado' => 'ACTIVO',
            'condicion' => 'HABIDO',
        ]),
    ]);

    $result = app(RucLookupService::class)->lookup('20601111222');

    expect($result)->toBe([
        'razon_social' => 'SERVICIOS INDUSTRIALES TRUJILLO S.A.C.',
        'direccion' => 'Av. Industrial 789',
        'estado_contribuyente' => 'ACTIVO',
        'condicion_domicilio' => 'HABIDO',
        'ubigeo' => null,
    ]);

    Http::assertSent(fn (Request $request) => $request->url() === 'https://dniruc.apisperu.com/api/v1/ruc/20601111222?token=test-token');
    Http::assertSentCount(1);
});

test('clientes index shows the latest sale date per client', function () {
    $user = vendedorUser();

    $client = Client::factory()->create();
    Sale::factory()->create([
        'client_id' => $client->id,
        'vendedor_id' => $user->id,
        'fecha' => '2026-01-10',
    ]);
    Sale::factory()->create([
        'client_id' => $client->id,
        'vendedor_id' => $user->id,
        'fecha' => '2026-03-15',
    ]);

    $response = $this->actingAs($user)
        ->get(route('vendedor.clientes.index', ['current_team' => $user->currentTeam]))
        ->assertOk();

    $row = collect($response->viewData('page')['props']['clients']['data'])
        ->firstWhere('id', $client->id);

    expect($row['ultima_compra'])->toBe('2026-03-15');
});

test('client search endpoint finds clients beyond the first page by name or document', function () {
    $user = vendedorUser();

    Client::factory()->count(10)->create();
    $buscado = Client::factory()->create([
        'razon_social' => 'RJHM S.A.C.',
        'numero_documento' => '20603335717',
    ]);

    $porNombre = $this->actingAs($user)
        ->getJson(route('vendedor.clientes.search', ['current_team' => $user->currentTeam, 'search' => 'RJHM']))
        ->assertOk();
    $porNombre->assertJsonFragment(['id' => $buscado->id]);

    $porDocumento = $this->actingAs($user)
        ->getJson(route('vendedor.clientes.search', ['current_team' => $user->currentTeam, 'search' => '20603335717']))
        ->assertOk();
    $porDocumento->assertJsonFragment(['id' => $buscado->id]);
});

test('client search endpoint matches every word of a name in any order', function () {
    $user = vendedorUser();

    $buscado = Client::factory()->create([
        'tipo_documento' => 'dni',
        'razon_social' => 'URCIA GUEVARA JOSE MIGUEL',
        'numero_documento' => '71234567',
    ]);
    Client::factory()->create(['razon_social' => 'JOSE PEREZ QUISPE']);

    $this->actingAs($user)
        ->getJson(route('vendedor.clientes.search', ['current_team' => $user->currentTeam, 'search' => 'jose urcia']))
        ->assertOk()
        ->assertJsonCount(1)
        ->assertJsonFragment(['id' => $buscado->id]);
});

test('creating a client from the sale or quote modal returns it as json', function () {
    $user = vendedorUser();

    $response = $this->actingAs($user)
        ->postJson(route('vendedor.clientes.store', ['current_team' => $user->currentTeam]), [
            'tipo_documento' => 'dni',
            'numero_documento' => '71234567',
            'razon_social' => 'URCIA GUEVARA JOSE MIGUEL',
            'activo' => true,
        ])
        ->assertCreated();

    $client = Client::where('numero_documento', '71234567')->firstOrFail();

    $response->assertExactJson([
        'id' => $client->id,
        'tipo_documento' => 'dni',
        'razon_social' => 'URCIA GUEVARA JOSE MIGUEL',
        'numero_documento' => '71234567',
    ]);
});

test('exportar clientes descarga un csv con los clientes de la busqueda actual', function () {
    $user = vendedorUser();
    Client::factory()->create(['razon_social' => 'EXTINTORES DEL NORTE S.A.C.', 'numero_documento' => '20600000001']);
    Client::factory()->create(['razon_social' => 'OTRA EMPRESA S.A.C.']);

    $response = $this->actingAs($user)
        ->get(route('vendedor.clientes.export', ['current_team' => $user->currentTeam, 'search' => 'norte']))
        ->assertOk()
        ->assertDownload('clientes-'.now()->format('Y-m-d').'.csv');

    $csv = $response->streamedContent();

    expect($csv)->toContain('EXTINTORES DEL NORTE S.A.C.')
        ->toContain('20600000001')
        ->not->toContain('OTRA EMPRESA');
});

test('client search endpoint filters from the first letter and ignores blank searches', function () {
    $user = vendedorUser();

    $cliente = Client::factory()->create(['razon_social' => 'ZAFIRO S.A.C.']);

    $this->actingAs($user)
        ->getJson(route('vendedor.clientes.search', ['current_team' => $user->currentTeam, 'search' => 'Z']))
        ->assertOk()
        ->assertJsonFragment(['id' => $cliente->id]);

    $this->actingAs($user)
        ->getJson(route('vendedor.clientes.search', ['current_team' => $user->currentTeam, 'search' => ' ']))
        ->assertOk()
        ->assertExactJson([]);
});

if (! function_exists('vendedorUser')) {
    function vendedorUser(): User
    {
        $user = User::factory()->create();
        $user->assignRole('Vendedor');

        return $user;
    }
}
