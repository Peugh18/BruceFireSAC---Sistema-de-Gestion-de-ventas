<?php

use App\Models\Client;
use App\Models\Product;
use App\Models\Quote;
use App\Models\Service;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('la nueva cotizacion no carga clientes ni catalogo: se buscan al escribir', function () {
    $user = vendedorUser();
    Client::factory()->create();
    Product::factory()->create(['activo' => true]);

    $this->actingAs($user)
        ->get(route('vendedor.cotizaciones.create', ['current_team' => $user->currentTeam]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('vendedor/cotizaciones/nueva')
            ->missing('clients')
            ->missing('products')
        );
});

test('la referencia de la cotizacion se guarda', function () {
    $user = vendedorUser();
    $item = Product::factory()->create(['precio_venta' => 100]);

    $this->actingAs($user)
        ->post(route('vendedor.cotizaciones.store', ['current_team' => $user->currentTeam]), [
            'client_id' => Client::factory()->create()->id,
            'fecha' => now()->toDateString(),
            'vigencia_hasta' => now()->addDays(15)->toDateString(),
            'referencia' => 'SEDE: Oficina Chimbote',
            'items' => [['product_id' => $item->id, 'cantidad' => 1, 'precio_unitario' => 100]],
        ])
        ->assertSessionHasNoErrors();

    expect(Quote::first()->referencia)->toBe('SEDE: Oficina Chimbote');
});

test('la condicion de pago propuesta solo acepta las opciones de la pantalla', function (string $condicion) {
    $user = vendedorUser();
    $item = Product::factory()->create(['precio_venta' => 100]);

    $this->actingAs($user)
        ->post(route('vendedor.cotizaciones.store', ['current_team' => $user->currentTeam]), [
            'client_id' => Client::factory()->create()->id,
            'fecha' => now()->toDateString(),
            'vigencia_hasta' => now()->addDays(15)->toDateString(),
            'condicion_pago_propuesta' => $condicion,
            'items' => [['product_id' => $item->id, 'cantidad' => 1, 'precio_unitario' => 100]],
        ])
        ->assertSessionHasNoErrors();

    expect(Quote::sole()->condicion_pago_propuesta)->toBe($condicion);
})->with(['Contado', 'Crédito 7 días', 'Crédito 15 días', 'Crédito 30 días']);

test('la condicion de pago propuesta rechaza texto libre con un mensaje claro', function () {
    $user = vendedorUser();
    $item = Product::factory()->create(['precio_venta' => 100]);

    $this->actingAs($user)
        ->post(route('vendedor.cotizaciones.store', ['current_team' => $user->currentTeam]), [
            'client_id' => Client::factory()->create()->id,
            'fecha' => now()->toDateString(),
            'vigencia_hasta' => now()->addDays(15)->toDateString(),
            'condicion_pago_propuesta' => 'ContadoDSADADA',
            'items' => [['product_id' => $item->id, 'cantidad' => 1, 'precio_unitario' => 100]],
        ])
        ->assertSessionHasErrors([
            'condicion_pago_propuesta' => 'Elige una condición de pago válida.',
        ]);

    expect(Quote::count())->toBe(0);
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

    expect((float) $quote->subtotal)->toEqual(169.49)
        ->and((float) $quote->igv)->toEqual(30.51)
        ->and((float) $quote->total)->toEqual(200.0)
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

test('enviar una cotizacion en borrador la emite y la deja enviada', function () {
    $user = vendedorUser();
    $quote = Quote::factory()->create(['vendedor_id' => $user->id, 'estado' => 'borrador']);

    $this->actingAs($user)
        ->post(route('vendedor.cotizaciones.send', ['current_team' => $user->currentTeam, 'quote' => $quote]))
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect($quote->refresh()->estado)->toBe('enviada');
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
