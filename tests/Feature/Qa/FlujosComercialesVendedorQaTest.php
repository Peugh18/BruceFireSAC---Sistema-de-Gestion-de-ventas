<?php

use App\Actions\Certificates\IssueCertificate;
use App\Actions\Sales\CreateSale;
use App\Models\CertificateType;
use App\Models\Client;
use App\Models\Equipment;
use App\Models\Installment;
use App\Models\Product;
use App\Models\Quote;
use App\Models\Sale;
use App\Models\SalePayment;
use App\Models\Sede;
use App\Models\Service;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

it('recorre clientes falsos de empresa, persona, varios y vehículo sin consultar APIs externas', function () {
    $empresa = Client::factory()->create([
        'razon_social' => 'EXTINTORES QA SAC',
        'numero_documento' => '20999999991',
    ]);
    $persona = Client::factory()->dni()->create([
        'razon_social' => 'PERSONA QA',
        'numero_documento' => '79999991',
    ]);
    $varios = Client::factory()->dni()->create([
        'razon_social' => 'CLIENTES VARIOS',
        'numero_documento' => '00000000',
    ]);
    $conVehiculo = Client::factory()->create(['razon_social' => 'TRANSPORTES QA SAC']);
    $vehicle = $conVehiculo->vehicles()->create([
        'placa' => 'QA-9999',
        'marca' => 'Marca QA',
        'modelo' => 'Modelo QA',
        'anio' => 2025,
    ]);

    expect($empresa->tipo_documento)->toBe('ruc')
        ->and($persona->tipo_documento)->toBe('dni')
        ->and($varios->razon_social)->toBe('CLIENTES VARIOS')
        ->and($vehicle->client_id)->toBe($conVehiculo->id);
});

it('convierte una cotización de servicio en venta y marca la cotización como vendida', function () {
    $vendedor = User::factory()->create();
    $vendedor->assignRole('Vendedor');
    $client = Client::factory()->create();
    $sede = Sede::factory()->almacen()->create();
    $service = Service::factory()->create(['nombre' => 'Inspección técnica QA']);
    $quote = Quote::factory()->create([
        'client_id' => $client->id,
        'vendedor_id' => $vendedor->id,
        'sede_id' => $sede->id,
        'estado' => 'enviada',
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
        'precio_unitario' => 150,
        'descuento' => 0,
    ]], $vendedor->id);

    expect($sale->quote_id)->toBe($quote->id)
        ->and($sale->items)->toHaveCount(1)
        ->and($quote->fresh()->estado)->toBe('convertida');
});

it('rechaza un cobro mayor al saldo y conserva cuota y pagos', function () {
    $vendedor = User::factory()->create();
    $vendedor->assignRole('Vendedor');
    $sale = Sale::factory()->create(['vendedor_id' => $vendedor->id]);
    $installment = Installment::factory()->create([
        'sale_id' => $sale->id,
        'monto' => 300,
        'estado' => 'parcial',
    ]);
    SalePayment::factory()->create([
        'sale_id' => $sale->id,
        'installment_id' => $installment->id,
        'monto' => 100,
        'fecha' => today(),
    ]);

    $this->actingAs($vendedor)
        ->post(route('vendedor.cobranzas.pagar', [
            'current_team' => $vendedor->currentTeam,
            'installment' => $installment,
        ]), [
            'monto' => 200.01,
            'forma_pago' => 'yape',
        ])
        ->assertSessionHasErrors(['monto' => 'El monto no puede superar el saldo de S/ 200.00.']);

    expect($installment->fresh()->estado)->toBe('parcial')
        ->and($installment->payments()->sum('monto'))->toEqual(100.0);
});

it('emite un certificado falso y muestra una alerta de vencimiento al vendedor', function () {
    $vendedor = User::factory()->create();
    $vendedor->assignRole('Vendedor');
    $client = Client::factory()->create();
    $type = CertificateType::factory()->create([
        'codigo' => 'operatividad_garantia',
        'vigencia_meses' => 12,
    ]);
    $certificate = app(IssueCertificate::class)->handle($type, $client, [[
        'numero_serie' => 'QA-CERT-001',
        'fecha_ultima_recarga' => today()->toDateString(),
    ]]);
    $equipment = Equipment::factory()->create([
        'client_id' => $client->id,
        'proxima_fecha_atencion' => today()->subDay(),
        'proxima_prueba_hidrostatica' => null,
    ]);

    $response = $this->actingAs($vendedor)
        ->get(route('vendedor.alertas.index', ['current_team' => $vendedor->currentTeam]))
        ->assertOk();

    $vencidas = collect($response->viewData('page')['props']['alerts']['vencidas']);

    expect($certificate->estado)->toBe('vigente')
        ->and($certificate->certificateUnits)->toHaveCount(1)
        ->and($vencidas->pluck('equipment_id'))->toContain($equipment->id);
});

it('debe rechazar precio cero en cotizaciones y ventas', function () {
    $vendedor = User::factory()->create();
    $vendedor->assignRole('Vendedor');
    $product = Product::factory()->create();

    $this->actingAs($vendedor)
        ->post(route('vendedor.cotizaciones.store', ['current_team' => $vendedor->currentTeam]), [
            'client_id' => Client::factory()->create()->id,
            'fecha' => today()->toDateString(),
            'vigencia_hasta' => today()->addDays(15)->toDateString(),
            'items' => [[
                'product_id' => $product->id,
                'cantidad' => 1,
                'precio_unitario' => 0,
            ]],
        ])
        ->assertSessionHasErrors('items.0.precio_unitario');
});

it('debe rechazar un descuento mayor al importe de la línea', function () {
    $vendedor = User::factory()->create();
    $vendedor->assignRole('Vendedor');
    $product = Product::factory()->create();

    $this->actingAs($vendedor)
        ->post(route('vendedor.cotizaciones.store', ['current_team' => $vendedor->currentTeam]), [
            'client_id' => Client::factory()->create()->id,
            'fecha' => today()->toDateString(),
            'vigencia_hasta' => today()->addDays(15)->toDateString(),
            'items' => [[
                'product_id' => $product->id,
                'cantidad' => 1,
                'precio_unitario' => 100,
                'descuento' => 150,
            ]],
        ])
        ->assertSessionHasErrors('items.0.descuento');
});
