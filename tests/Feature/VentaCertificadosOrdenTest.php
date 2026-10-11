<?php

use App\Models\Certificate;
use App\Models\Client;
use App\Models\Sale;
use App\Models\Sede;
use App\Models\ServiceOrder;
use App\Services\Certificates\CertificadosDeVenta;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('la venta muestra los certificados de su orden emitidos antes del cobro sin duplicarlos ni modificar su vinculo', function () {
    $user = vendedorUser();
    $client = Client::factory()->create();
    $sale = Sale::factory()->create(['client_id' => $client->id, 'vendedor_id' => $user->id]);
    $order = ServiceOrder::factory()->create(['client_id' => $client->id, 'sale_id' => $sale->id, 'sede_id' => $sale->sede_id]);
    $previous = Certificate::factory()->create(['client_id' => $client->id, 'service_order_id' => $order->id, 'sale_id' => null]);
    $direct = Certificate::factory()->create(['client_id' => $client->id, 'service_order_id' => $order->id, 'sale_id' => $sale->id]);
    $otherOrder = ServiceOrder::factory()->create(['client_id' => $client->id]);
    Certificate::factory()->create(['client_id' => $client->id, 'service_order_id' => $otherOrder->id, 'sale_id' => null]);

    $this->actingAs($user)->get(route('vendedor.ventas.show', ['current_team' => $user->currentTeam, 'sale' => $sale]))
        ->assertOk()->assertInertia(fn ($page) => $page
        ->has('certificados', 2)
        ->where('certificados.0.id', $previous->id)
        ->where('certificados.1.id', $direct->id));

    expect($previous->fresh()->sale_id)->toBeNull();
});

test('el enlace de certificados no permite abrir una venta de otra sede', function () {
    $user = vendedorUser();
    $user->update(['sede_id' => Sede::factory()->create()->id]);
    $sale = Sale::factory()->create(['vendedor_id' => $user->id, 'sede_id' => Sede::factory()->create()->id]);
    $order = ServiceOrder::factory()->create(['sale_id' => $sale->id]);
    Certificate::factory()->create(['service_order_id' => $order->id, 'sale_id' => null]);

    $this->actingAs($user)->get(route('vendedor.ventas.show', ['current_team' => $user->currentTeam, 'sale' => $sale]))
        ->assertNotFound();
});

test('los certificados de orden no se ofrecen como revisiones de certificados emitidos por venta', function () {
    $sale = Sale::factory()->create();
    $order = ServiceOrder::factory()->create(['client_id' => $sale->client_id, 'sale_id' => $sale->id, 'sede_id' => $sale->sede_id]);
    Certificate::factory()->create(['client_id' => $sale->client_id, 'service_order_id' => $order->id, 'sale_id' => null]);
    $direct = Certificate::factory()->create(['client_id' => $sale->client_id, 'sale_id' => $sale->id]);

    $editable = CertificadosDeVenta::deLaVenta($sale);
    expect($editable)->toHaveCount(1)->and($editable[0]['id'])->toBe($direct->id);
    expect(CertificadosDeVenta::deLaVenta($sale, incluirOrdenes: true))->toHaveCount(2);
});
