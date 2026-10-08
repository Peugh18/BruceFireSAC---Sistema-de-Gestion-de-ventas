<?php

use App\Actions\Cotizaciones\CreateQuote;
use App\Actions\Sales\CreateSale;
use App\Models\Client;
use App\Models\Quote;
use App\Models\Sede;
use App\Models\Service;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

test('una cotizacion con lineas exoneradas no inventa IGV y sus totales cuadran con la venta', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $vendedor = User::factory()->create();
    $vendedor->assignRole('Vendedor');
    $client = Client::factory()->create();
    $sede = Sede::factory()->almacen()->create();

    // Un servicio gravado (10) y otro exonerado (20). Antes la cotización
    // aplicaba IGV a las dos líneas y al venderla los totales no cuadraban.
    $gravado = Service::factory()->create(['tipo_afectacion_igv' => '10', 'aplica_igv' => true]);
    $exonerado = Service::factory()->create(['tipo_afectacion_igv' => '20', 'aplica_igv' => false]);

    $quote = app(CreateQuote::class)->handle([
        'client_id' => $client->id,
        'sede_id' => $sede->id,
        'fecha' => today()->toDateString(),
        'vigencia_hasta' => today()->addDays(15)->toDateString(),
    ], [
        ['service_id' => $gravado->id, 'cantidad' => 1, 'precio_unitario' => 118.00, 'descuento' => 0],
        ['service_id' => $exonerado->id, 'cantidad' => 1, 'precio_unitario' => 100.00, 'descuento' => 0],
    ], $vendedor->id);

    // Solo la línea gravada lleva IGV: 118 = 100.00 de base + 18.00 de IGV.
    // La exonerada entra entera al subtotal sin IGV.
    expect((float) $quote->subtotal)->toBe(200.00)
        ->and((float) $quote->igv)->toBe(18.00)
        ->and((float) $quote->total)->toBe(218.00)
        ->and($quote->items()->firstWhere('service_id', $exonerado->id)->tipo_afectacion_igv)->toBe('20');

    $sale = app(CreateSale::class)->handle([
        'quote_id' => $quote->id,
        'client_id' => $client->id,
        'sede_id' => $sede->id,
        'fecha' => today()->toDateString(),
        'destino' => 'local_cliente',
        'condicion_pago' => 'contado',
        'comprobante_tipo' => 'nota_venta',
    ], [
        ['tipo_linea' => 'servicio', 'service_id' => $gravado->id, 'cantidad' => 1, 'precio_unitario' => 118.00, 'descuento' => 0],
        ['tipo_linea' => 'servicio', 'service_id' => $exonerado->id, 'cantidad' => 1, 'precio_unitario' => 100.00, 'descuento' => 0],
    ], $vendedor->id);

    // La venta derivada cuadra exactamente con lo cotizado.
    expect((float) $sale->subtotal)->toBe((float) $quote->subtotal)
        ->and((float) $sale->igv)->toBe((float) $quote->igv)
        ->and((float) $sale->total)->toBe((float) $quote->total);
});

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
