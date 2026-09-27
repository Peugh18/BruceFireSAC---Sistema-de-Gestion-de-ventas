<?php

use App\Actions\Sales\CreateSale;
use App\Models\Client;
use App\Models\Quote;
use App\Models\Sede;
use App\Models\Service;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

test('una cotizacion vigente pasa a venta sin aceptarla antes', function (string $estado) {
    $this->seed(RolesAndPermissionsSeeder::class);
    $vendedor = User::factory()->create();
    $vendedor->assignRole('Vendedor');
    $client = Client::factory()->create();
    $sede = Sede::factory()->almacen()->create();
    $service = Service::factory()->create();
    $quote = Quote::factory()->create([
        'vendedor_id' => $vendedor->id,
        'client_id' => $client->id,
        'sede_id' => $sede->id,
        'estado' => $estado,
    ]);

    $sale = app(CreateSale::class)->handle([
        'quote_id' => $quote->id,
        'client_id' => $client->id,
        'sede_id' => $sede->id,
        'fecha' => today()->toDateString(),
        'destino' => 'local_cliente',
        'condicion_pago' => 'contado',
        'comprobante_tipo' => 'nota_venta',
    ], [[
        'tipo_linea' => 'servicio',
        'service_id' => $service->id,
        'cantidad' => 1,
        'precio_unitario' => 100,
        'descuento' => 0,
    ]], $vendedor->id);

    expect($sale->quote_id)->toBe($quote->id)
        ->and($quote->refresh()->estado)->toBe('convertida');
})->with(['borrador', 'emitida', 'enviada']);
