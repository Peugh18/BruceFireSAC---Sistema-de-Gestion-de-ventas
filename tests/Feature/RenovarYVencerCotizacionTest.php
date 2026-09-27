<?php

use App\Models\Quote;
use App\Models\Service;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

test('vence cotizaciones automaticamente y prepara una renovacion por quince dias', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->travelTo('2026-09-27 10:00:00');
    $vendedor = User::factory()->create();
    $vendedor->assignRole('Vendedor');
    $quote = Quote::factory()->create([
        'estado' => 'emitida',
        'vigencia_hasta' => today()->subDay(),
    ]);
    $rechazada = Quote::factory()->create([
        'estado' => 'rechazada',
        'vigencia_hasta' => today()->subDay(),
    ]);
    $convertida = Quote::factory()->create([
        'estado' => 'convertida',
        'vigencia_hasta' => today()->subDay(),
    ]);
    $service = Service::factory()->create();
    $quote->items()->create([
        'service_id' => $service->id,
        'cantidad' => 2,
        'precio_unitario' => 80,
        'descuento' => 10,
        'subtotal' => 150,
    ]);

    $this->artisan('quotes:expire')->assertSuccessful();
    expect($quote->refresh()->estado)->toBe('vencida')
        ->and($rechazada->refresh()->estado)->toBe('rechazada')
        ->and($convertida->refresh()->estado)->toBe('convertida');

    $this->actingAs($vendedor)
        ->get(route('vendedor.cotizaciones.create', [
            'current_team' => $vendedor->currentTeam,
            'renovar' => $quote->id,
        ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('vendedor/cotizaciones/nueva')
            ->where('renovacion.numero', $quote->numero)
            ->where('renovacion.items.0.cantidad', 2)
        );
});
