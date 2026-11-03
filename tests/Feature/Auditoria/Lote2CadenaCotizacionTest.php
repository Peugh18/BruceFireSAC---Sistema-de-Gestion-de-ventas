<?php

use App\Actions\Sales\CreateSale;
use App\Models\Client;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\Quote;
use App\Models\QuoteItem;
use App\Models\Sale;
use App\Models\Sede;
use App\Models\Service;
use App\Models\ServiceOrder;
use App\Models\User;
use App\Services\Billing\GreenterService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    config(['billing.exigir_cotizacion' => true]);
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->sede = Sede::factory()->almacen()->create();
    $this->vendedor = User::factory()->create(['sede_id' => $this->sede->id]);
    $this->vendedor->assignRole('Vendedor');
    $this->team = ['current_team' => $this->vendedor->currentTeam];
    $this->venta = fn (array $data, array $items) => app(CreateSale::class)->handle([
        'sede_id' => $this->sede->id, 'fecha' => today()->toDateString(), 'destino' => 'local_cliente',
        'condicion_pago' => 'contado', 'comprobante_tipo' => 'nota_venta', ...$data,
    ], $items, $this->vendedor->id);
});

// P-002: aceptar exige quién, por qué canal y, si fue por orden de compra, su número.
it('registra la aceptación del cliente con su orden de compra', function () {
    $quote = Quote::factory()->create(['sede_id' => $this->sede->id, 'estado' => 'enviada']);

    $this->actingAs($this->vendedor)
        ->post(route('vendedor.cotizaciones.accept', [...$this->team, 'quote' => $quote]), ['aceptada_canal' => 'orden_compra'])
        ->assertSessionHasErrors(['aceptada_por', 'orden_compra']);
    expect($quote->fresh()->estado)->toBe('enviada');

    $this->actingAs($this->vendedor)
        ->post(route('vendedor.cotizaciones.accept', [...$this->team, 'quote' => $quote]), [
            'aceptada_por' => 'Rosa Díaz, administradora', 'aceptada_canal' => 'orden_compra', 'orden_compra' => 'oc-4500123',
        ])->assertSessionHasNoErrors();

    $quote->refresh();
    expect($quote->estado)->toBe('aceptada')
        ->and($quote->aceptada_por)->toBe('Rosa Díaz, administradora')
        ->and($quote->orden_compra)->toBe('OC-4500123')
        ->and($quote->aceptada_at)->not->toBeNull();
});

it('el cliente puede aceptar en tienda una cotización que no se le envió', function () {
    $quote = Quote::factory()->create(['sede_id' => $this->sede->id, 'estado' => 'borrador']);

    $this->actingAs($this->vendedor)
        ->post(route('vendedor.cotizaciones.accept', [...$this->team, 'quote' => $quote]), [
            'aceptada_por' => 'Cliente en mostrador', 'aceptada_canal' => 'presencial',
        ])->assertSessionHasNoErrors();

    expect($quote->fresh()->estado)->toBe('aceptada');
});

it('una cotización sin aceptar no pasa a venta', function (string $estado) {
    $client = Client::factory()->create();
    $quote = Quote::factory()->create(['client_id' => $client->id, 'sede_id' => $this->sede->id, 'estado' => $estado]);
    expect(fn () => ($this->venta)(['quote_id' => $quote->id, 'client_id' => $client->id], [
        ['tipo_linea' => 'servicio', 'service_id' => Service::factory()->create()->id, 'cantidad' => 1, 'precio_unitario' => 35, 'descuento' => 0],
    ]))->toThrow(ValidationException::class, 'registra primero que el cliente aceptó');

    expect($quote->fresh()->estado)->toBe($estado);
})->with(['borrador', 'emitida', 'enviada']);

it('una cotización sin aceptar no abre la orden de taller', function () {
    $quote = Quote::factory()->create(['sede_id' => $this->sede->id, 'estado' => 'enviada']);
    QuoteItem::factory()->create(['quote_id' => $quote->id, 'product_id' => null, 'service_id' => Service::factory()->create()->id]);

    $this->actingAs($this->vendedor)
        ->post(route('vendedor.cotizaciones.orden-taller', [...$this->team, 'quote' => $quote]))
        ->assertSessionHasErrors('quote_id');

    expect(ServiceOrder::count())->toBe(0);
});

// P-022: «Nueva orden» nace de una cotización aceptada y copia sus datos.
it('nueva orden de servicio sale de una cotización aceptada', function () {
    $client = Client::factory()->create();
    $servicio = Service::factory()->create();
    $quote = Quote::factory()->aceptada()->create(['client_id' => $client->id, 'sede_id' => $this->sede->id, 'aceptada_por' => 'Rosa']);
    QuoteItem::factory()->create(['quote_id' => $quote->id, 'product_id' => null, 'service_id' => $servicio->id, 'cantidad' => 3]);

    $this->actingAs($this->vendedor)
        ->post(route('vendedor.ordenes-servicio.store', $this->team), ['fecha' => today()->toDateString(), 'departamento_tecnico' => 'planta'])
        ->assertSessionHasErrors('quote_id');

    $this->actingAs($this->vendedor)
        ->post(route('vendedor.ordenes-servicio.store', $this->team), [
            'quote_id' => $quote->id, 'fecha' => today()->toDateString(), 'departamento_tecnico' => 'campo',
        ])->assertSessionHasNoErrors();

    $orden = ServiceOrder::sole();
    expect($orden->quote_id)->toBe($quote->id)
        ->and($orden->client_id)->toBe($client->id)
        ->and($orden->service_id)->toBe($servicio->id)
        ->and($orden->departamento_tecnico)->toBe('campo')
        ->and((int) $orden->lines()->sum('cantidad'))->toBe(3);
});

// D-10 y P-015: servicios y empresas con RUC se venden desde una cotización.
it('un servicio no se vende sin cotización', function () {
    $persona = Client::factory()->dni()->create();

    expect(fn () => ($this->venta)(['client_id' => $persona->id], [
        ['tipo_linea' => 'servicio', 'service_id' => Service::factory()->create()->id, 'cantidad' => 1, 'precio_unitario' => 35, 'descuento' => 0],
    ]))->toThrow(ValidationException::class, 'Los servicios se venden desde una cotización aceptada');
});

it('a una empresa con RUC no se le vende sin cotización', function () {
    $empresa = Client::factory()->create(['tipo_documento' => 'ruc']);

    expect(fn () => ($this->venta)(['client_id' => $empresa->id], [
        ['tipo_linea' => 'producto', 'product_id' => Product::factory()->create(['serializado' => false])->id, 'cantidad' => 1, 'precio_unitario' => 10, 'descuento' => 0],
    ]))->toThrow(ValidationException::class, 'empresa con RUC');
});

it('lo que compra una persona en mostrador se vende directo', function () {
    $persona = Client::factory()->dni()->create();
    $producto = Product::factory()->create(['serializado' => false]);
    InventoryMovement::create(['product_id' => $producto->id, 'sede_id' => $this->sede->id, 'tipo' => 'ingreso', 'cantidad' => 3, 'user_id' => $this->vendedor->id]);

    $sale = ($this->venta)(['client_id' => $persona->id], [
        ['tipo_linea' => 'producto', 'product_id' => $producto->id, 'cantidad' => 1, 'precio_unitario' => 10, 'descuento' => 0],
    ]);

    expect($sale)->toBeInstanceOf(Sale::class);
});

// D-11: la orden de compra del cliente viaja al XML de la factura.
it('la factura lleva la orden de compra del cliente', function () {
    $quote = Quote::factory()->aceptada()->create(['orden_compra' => 'OC-4500123']);
    $sale = Sale::factory()->create(['quote_id' => $quote->id, 'client_id' => $quote->client_id]);
    $documento = $sale->electronicDocuments()->create(['tipo' => 'factura', 'serie' => 'F001', 'correlativo' => 99, 'fecha_emision' => today(), 'sunat_estado' => 'por_enviar']);

    expect(app(GreenterService::class)->buildInvoice($sale, $documento)->getCompra())->toBe('OC-4500123');
});
