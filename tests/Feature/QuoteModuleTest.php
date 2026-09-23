<?php

use App\Models\Client;
use App\Models\Product;
use App\Models\Quote;
use App\Models\Service;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('vendedor can open the nueva cotizacion page with catalog data', function () {
    $user = vendedorUser();
    Client::factory()->create();
    Product::factory()->create(['activo' => true]);

    $this->actingAs($user)
        ->get(route('vendedor.cotizaciones.create', ['current_team' => $user->currentTeam]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('vendedor/cotizaciones/nueva')
            ->has('clients')
            ->has('products')
            ->has('services')
        );
});

test('vendedor creates a quote with items and totals are computed', function () {
    $user = vendedorUser();
    $client = Client::factory()->create();
    $item = Product::factory()->create(['precio_venta' => 100]);

    $response = $this
        ->actingAs($user)
        ->post(route('vendedor.cotizaciones.store', ['current_team' => $user->currentTeam]), [
            'client_id' => $client->id,
            'fecha' => now()->toDateString(),
            'vigencia_hasta' => now()->addDays(15)->toDateString(),
            'items' => [
                ['product_id' => $item->id, 'cantidad' => 2, 'precio_unitario' => 100],
            ],
        ]);

    $response->assertSessionHasNoErrors();

    $quote = Quote::firstOrFail();

    expect($quote->subtotal)->toEqual(200.0)
        ->and($quote->igv)->toEqual(36.0)
        ->and($quote->total)->toEqual(236.0)
        ->and($quote->estado)->toBe('borrador')
        ->and($quote->items)->toHaveCount(1);
});

test('a quote cannot skip states', function () {
    $quote = Quote::factory()->create(['estado' => 'borrador']);

    expect($quote->canTransitionTo('aceptada'))->toBeFalse()
        ->and($quote->canTransitionTo('emitida'))->toBeTrue();
});

test('vendedor can send, then accept a quote, but not accept directly from borrador', function () {
    $user = vendedorUser();
    $quote = Quote::factory()->create(['vendedor_id' => $user->id, 'estado' => 'borrador']);

    $this->actingAs($user)
        ->post(route('vendedor.cotizaciones.accept', ['current_team' => $user->currentTeam, 'quote' => $quote]))
        ->assertSessionHasErrors();

    expect($quote->refresh()->estado)->toBe('borrador');
});

test('buscar catalogo en nueva cotizacion encuentra productos y servicios fuera del listado inicial', function () {
    $user = vendedorUser();

    Product::factory()->count(8)->create(['activo' => true]);
    Service::factory()->count(8)->create(['activo' => true]);

    $productoBuscado = Product::factory()->create([
        'nombre' => 'EXTINTOR PQS-ABC DE 6KG',
        'activo' => true,
    ]);
    $servicioBuscado = Service::factory()->create([
        'nombre' => 'RECARGA DE EXTINTOR PQS-ABC',
        'activo' => true,
    ]);

    $response = $this->actingAs($user)
        ->getJson(route('vendedor.cotizaciones.buscar-catalogo', [
            'current_team' => $user->currentTeam,
            'search' => 'EXTINTOR',
        ]))
        ->assertOk();

    $response->assertJsonFragment(['id' => $productoBuscado->id, 'tipo' => 'product'])
        ->assertJsonFragment(['id' => $servicioBuscado->id, 'tipo' => 'service']);
});
