<?php

use App\Models\Client;
use App\Models\Product;
use App\Models\Quote;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
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
