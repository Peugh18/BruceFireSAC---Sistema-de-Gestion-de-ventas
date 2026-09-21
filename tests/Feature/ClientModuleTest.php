<?php

use App\Models\Client;
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
            'departamento' => 'La Libertad',
            'provincia' => 'Trujillo',
            'distrito' => 'Trujillo',
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
    ]);

    Http::assertSent(fn (Request $request) => $request->url() === 'https://dniruc.apisperu.com/api/v1/ruc/20601111222?token=test-token');
    Http::assertSentCount(1);
});

if (! function_exists('vendedorUser')) {
    function vendedorUser(): User
    {
        $user = User::factory()->create();
        $user->assignRole('Vendedor');

        return $user;
    }
}
