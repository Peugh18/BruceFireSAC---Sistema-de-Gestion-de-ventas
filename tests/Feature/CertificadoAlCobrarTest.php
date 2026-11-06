<?php

use App\Actions\TecnicoPlanta\ExecuteAndCloseServiceOrder;
use App\Models\CashRegister;
use App\Models\Certificate;
use App\Models\Client;
use App\Models\Equipment;
use App\Models\Quote;
use App\Models\QuoteItem;
use App\Models\Service;
use App\Models\ServiceOrder;
use Database\Seeders\CertificateTypeSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(CertificateTypeSeeder::class);
    $this->vendedor = vendedorUser();
    CashRegister::factory()->create(['vendedor_id' => $this->vendedor->id]);
    $this->team = ['current_team' => $this->vendedor->currentTeam];

    // Recojo de campo sin cotización: planta lo termina antes de que se cobre.
    $cliente = Client::factory()->create(['direccion_fiscal' => 'Av. España 1234, Trujillo']);
    $this->orden = ServiceOrder::factory()->create(['client_id' => $cliente->id, 'sede_id' => $this->vendedor->sede_id, 'estado' => 'datos_completos', 'sale_id' => null, 'quote_id' => null]);
    $this->equipo = Equipment::factory()->create(['client_id' => $cliente->id]);
    $this->orden->equipments()->attach($this->equipo, ['recibido' => true]);
    $this->servicio = Service::factory()->create(['precio_venta' => 35, 'nombre' => 'Servicio general', 'agente' => null, 'capacidad' => null]);
});

test('planta termina sin cobro: el certificado espera al comprobante', function () {
    app(ExecuteAndCloseServiceOrder::class)->advanceState($this->orden, 'listo_certificado', $this->vendedor);

    expect(Certificate::where('service_order_id', $this->orden->id)->count())->toBe(0)
        ->and($this->orden->fresh()->estado)->toBe('listo_certificado');

    $this->actingAs($this->vendedor)->get(route('vendedor.ordenes-servicio.show', [...$this->team, 'service_order' => $this->orden]))
        ->assertInertia(fn (Assert $page) => $page->where('certificadoAlCobrar', true));
});

test('al emitir la nota de venta de la orden sale su certificado, sin pedir cotizacion', function () {
    app(ExecuteAndCloseServiceOrder::class)->advanceState($this->orden, 'listo_certificado', $this->vendedor);

    $this->actingAs($this->vendedor)->post(route('vendedor.ventas.store', $this->team), [
        'service_order_id' => $this->orden->id,
        'client_id' => $this->orden->client_id,
        'sede_id' => $this->vendedor->sede_id,
        'fecha' => now()->toDateString(),
        'destino' => 'local_cliente',
        'condicion_pago' => 'contado',
        'medio_pago' => 'efectivo',
        'comprobante_tipo' => 'nota_venta',
        'emitir' => true,
        'items' => [['tipo_linea' => 'recarga_servicio', 'numero_serie' => $this->equipo->numero_serie, 'service_id' => $this->servicio->id, 'cantidad' => 1, 'precio_unitario' => 35]],
    ])->assertSessionHasNoErrors();

    expect($this->orden->fresh()->sale_id)->not->toBeNull()
        ->and(Certificate::where('service_order_id', $this->orden->id)->whereHas('certificateType', fn ($q) => $q->where('codigo', 'operatividad_garantia'))->count())->toBe(1);
});

test('al vender una instalacion cotizada la orden le llega a campo', function () {
    $instalacion = Service::factory()->create(['nombre' => 'Instalación de extintores', 'precio_venta' => 20, 'certificate_type_id' => null]);
    $quote = Quote::factory()->create(['client_id' => $this->orden->client_id, 'sede_id' => $this->vendedor->sede_id, 'estado' => 'aceptada']);
    QuoteItem::factory()->create(['quote_id' => $quote->id, 'service_id' => $instalacion->id, 'product_id' => null, 'cantidad' => 1, 'precio_unitario' => 20]);

    $this->actingAs($this->vendedor)->post(route('vendedor.ventas.store', $this->team), [
        'quote_id' => $quote->id,
        'client_id' => $quote->client_id,
        'sede_id' => $this->vendedor->sede_id,
        'fecha' => now()->toDateString(),
        'destino' => 'local_cliente',
        'condicion_pago' => 'contado',
        'medio_pago' => 'efectivo',
        'comprobante_tipo' => 'nota_venta',
        'emitir' => true,
        'items' => [['tipo_linea' => 'servicio', 'service_id' => $instalacion->id, 'cantidad' => 1, 'precio_unitario' => 20]],
    ])->assertSessionHasNoErrors();

    expect(ServiceOrder::where('quote_id', $quote->id)->sole()->departamento_tecnico)->toBe('campo');
});

test('lo que recogio campo vuelve a campo cuando planta lo deja listo para entregar', function () {
    $this->orden->update(['departamento_tecnico' => 'planta', 'tecnico_id' => $this->vendedor->id]);
    $this->orden->events()->create(['tipo' => 'otro', 'user_id' => $this->vendedor->id, 'payload' => ['eslabon_custodia' => 'recojo_campo']]);
    $taller = app(ExecuteAndCloseServiceOrder::class);
    $taller->advanceState($this->orden, 'listo_certificado', $this->vendedor);
    $taller->advanceState($this->orden->fresh(), 'listo_entrega', $this->vendedor);

    expect($this->orden->fresh())
        ->departamento_tecnico->toBe('campo')
        ->tecnico_id->toBeNull();
});
