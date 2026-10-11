<?php

use App\Models\Certificate;
use App\Models\Client;
use App\Models\Quote;
use App\Models\Sede;
use App\Models\ServiceOrder;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('la ficha de orden incluye su cotizacion y certificados sin mezclar otra orden', function () {
    $user = vendedorUser();
    $client = Client::factory()->create();
    $quote = Quote::factory()->create(['client_id' => $client->id]);
    $order = ServiceOrder::factory()->create(['client_id' => $client->id, 'quote_id' => $quote->id]);
    $certificate = Certificate::factory()->create(['client_id' => $client->id, 'service_order_id' => $order->id]);
    Certificate::factory()->create(['client_id' => $client->id, 'service_order_id' => ServiceOrder::factory()->create()->id]);

    $this->actingAs($user)
        ->get(route('vendedor.ordenes-servicio.show', ['current_team' => $user->currentTeam, 'service_order' => $order]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('serviceOrder.quote.id', $quote->id)
            ->where('serviceOrder.quote.numero', $quote->numero)
            ->has('serviceOrder.certificates', 1)
            ->where('serviceOrder.certificates.0.id', $certificate->id)
            ->where('serviceOrder.certificates.0.estado', $certificate->estado)
        );
});

test('los documentos relacionados no permiten abrir una orden de otra sede', function () {
    $user = vendedorUser();
    $user->update(['sede_id' => Sede::factory()->create()->id]);
    $order = ServiceOrder::factory()->create(['sede_id' => Sede::factory()->create()->id]);
    Certificate::factory()->create(['service_order_id' => $order->id]);

    $this->actingAs($user)
        ->get(route('vendedor.ordenes-servicio.show', ['current_team' => $user->currentTeam, 'service_order' => $order]))
        ->assertNotFound();
});
