<?php

use App\Actions\Cotizaciones\CreateQuote;
use App\Actions\Sales\CreateSale;
use App\Actions\TecnicoPlanta\ExecuteAndCloseServiceOrder;
use App\Models\Deficiency;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\QuoteItem;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Sede;
use App\Models\Service;
use App\Models\ServiceOrder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function adicionalPendiente(): array
{
    $user = vendedorUser();
    $service = Service::factory()->create(['precio_venta' => 50]);
    $sede = Sede::factory()->almacen()->create();
    $order = ServiceOrder::factory()->create(['sede_id' => $sede->id, 'service_id' => $service->id, 'estado' => 'esperando_autorizacion']);
    $quote = app(CreateQuote::class)->handle([
        'client_id' => $order->client_id, 'sede_id' => $order->sede_id,
        'fecha' => today(), 'vigencia_hasta' => today()->addDays(15),
    ], [['service_id' => $service->id, 'cantidad' => 1, 'precio_unitario' => 50]], $user->id);
    $order->update(['quote_id' => $quote->id]);
    $deficiency = Deficiency::factory()->create(['service_order_id' => $order->id, 'estado' => 'esperando_autorizacion']);
    $product = Product::factory()->create(['serializado' => false, 'precio_venta' => 15]);

    return [$user, $order, $quote, $deficiency, $product];
}

test('cotizar repuesto agrega su precio del catalogo a la cotizacion existente sin duplicar al editar', function () {
    [$user, $order, $quote, $deficiency, $product] = adicionalPendiente();
    $url = route('vendedor.deficiencias.cotizar', [$user->currentTeam, $deficiency]);
    $this->actingAs($user)->post($url, ['product_id' => $product->id, 'cantidad' => 1])->assertSessionHasNoErrors();
    expect((float) $quote->refresh()->total)->toBe(65.0);
    $this->post($url, ['product_id' => $product->id, 'cantidad' => 2, 'precio_unitario' => 12])->assertSessionHasNoErrors();
    expect((float) $quote->refresh()->total)->toBe(74.0)
        ->and(QuoteItem::where('deficiency_id', $deficiency->id)->count())->toBe(1)
        ->and($order->events()->where('payload->accion', 'adicional_cotizado')->count())->toBe(2);
});

test('autorizacion usa el importe de la linea y no el total de toda la cotizacion', function () {
    [$user, $order, $quote, $deficiency, $product] = adicionalPendiente();
    $this->actingAs($user)->post(route('vendedor.deficiencias.cotizar', [$user->currentTeam, $deficiency]), ['product_id' => $product->id, 'cantidad' => 1]);
    $this->post(route('vendedor.deficiencias.autorizar', [$user->currentTeam, $deficiency]), [
        'autorizado' => true, 'autorizado_por' => 'Cliente', 'canal' => 'whatsapp',
        'fecha' => today()->toDateString(), 'cotizacion_adicional_id' => $quote->id,
    ])->assertSessionHasNoErrors();
    expect((float) $deficiency->refresh()->authorization->importe)->toBe(15.0);
});

test('importe sin linea cotizada no autoriza un adicional que podria perderse', function () {
    [$user, $order, $quote, $deficiency] = adicionalPendiente();
    $this->actingAs($user)->post(route('vendedor.deficiencias.autorizar', [$user->currentTeam, $deficiency]), [
        'autorizado' => true, 'autorizado_por' => 'Cliente', 'canal' => 'whatsapp', 'fecha' => today()->toDateString(), 'importe' => 35,
    ])->assertSessionHasErrors('cotizacion_adicional_id');
    expect($deficiency->refresh()->estado)->toBe('esperando_autorizacion');
});

test('cotizacion convertida y orden anulada no admiten nuevas lineas', function () {
    [$user, $order, $quote, $deficiency, $product] = adicionalPendiente();
    $quote->update(['estado' => 'convertida']);
    $this->actingAs($user)->post(route('vendedor.deficiencias.cotizar', [$user->currentTeam, $deficiency]), ['quote_id' => $quote->id, 'product_id' => $product->id, 'cantidad' => 1])->assertSessionHasErrors('quote_id');
    $order->update(['estado' => 'anulada']);
    $this->post(route('vendedor.deficiencias.cotizar', [$user->currentTeam, $deficiency]), ['product_id' => $product->id, 'cantidad' => 1])->assertSessionHasErrors('deficiency');
});

test('la cotizacion abre taller antes de vender y repetir no duplica la orden', function () {
    [$user, $orden, $quote] = adicionalPendiente();
    $orden->deficiencies()->delete();
    $orden->delete();
    $url = route('vendedor.cotizaciones.orden-taller', [$user->currentTeam, $quote]);
    $this->actingAs($user)->post($url)->assertSessionHasNoErrors();
    $this->post($url)->assertSessionHasNoErrors();
    expect($quote->serviceOrders()->count())->toBe(1)
        ->and($quote->serviceOrders()->first()->sale_id)->toBeNull()
        ->and($quote->sale()->exists())->toBeFalse();
});

test('convertir la cotizacion exige el adicional autorizado y no lo cobra de nuevo', function () {
    [$user, $order, $quote, $deficiency, $product] = adicionalPendiente();
    $this->actingAs($user)->post(route('vendedor.deficiencias.cotizar', [$user->currentTeam, $deficiency]), ['product_id' => $product->id, 'cantidad' => 1]);
    $this->post(route('vendedor.deficiencias.autorizar', [$user->currentTeam, $deficiency]), ['autorizado' => true, 'autorizado_por' => 'Cliente', 'canal' => 'whatsapp', 'fecha' => today()->toDateString()])->assertSessionHasNoErrors();
    $data = ['quote_id' => $quote->id, 'client_id' => $order->client_id, 'sede_id' => $order->sede_id, 'fecha' => today(), 'destino' => 'local_cliente', 'condicion_pago' => 'contado', 'comprobante_tipo' => 'nota_venta'];
    $base = ['tipo_linea' => 'servicio', 'service_id' => $order->service_id, 'cantidad' => 1, 'precio_unitario' => 50];
    expect(fn () => app(CreateSale::class)->handle($data, [$base], $user->id))->toThrow(ValidationException::class);
    $extra = ['tipo_linea' => 'producto', 'product_id' => $product->id, 'deficiency_id' => $deficiency->id, 'cantidad' => 1, 'precio_unitario' => 15];
    $sale = app(CreateSale::class)->handle($data, [$base, $extra], $user->id);
    expect((float) $sale->total)->toBe(65.0)->and($sale->items()->where('deficiency_id', $deficiency->id)->count())->toBe(1);
    unset($data['quote_id']);
    expect(fn () => app(CreateSale::class)->handle($data, [$extra], $user->id))->toThrow(ValidationException::class);
});

test('corregir la cotizacion retira el adicional con historial y sin cambiar consumos fisicos', function () {
    [$user, $order, $quote, $deficiency, $product] = adicionalPendiente();
    $this->actingAs($user)->post(route('vendedor.deficiencias.cotizar', [$user->currentTeam, $deficiency]), ['product_id' => $product->id, 'cantidad' => 1]);
    $this->post(route('vendedor.deficiencias.autorizar', [$user->currentTeam, $deficiency]), ['autorizado' => true, 'autorizado_por' => 'Cliente', 'canal' => 'whatsapp', 'fecha' => today()->toDateString()]);
    $this->put(route('vendedor.cotizaciones.update', [$user->currentTeam, $quote]), ['client_id' => $order->client_id, 'fecha' => today()->toDateString(), 'vigencia_hasta' => today()->addDays(15)->toDateString(), 'items' => [['service_id' => $order->service_id, 'cantidad' => 1, 'precio_unitario' => 50]]])->assertSessionHasNoErrors();
    expect((float) $quote->refresh()->total)->toBe(50.0)->and($deficiency->refresh()->estado)->toBe('rechazada')
        ->and($order->events()->where('payload->accion', 'adicional_corregido')->exists())->toBeTrue();
});

test('corregir precio de una reparacion instalada conserva resolucion y no permite otro consumo', function () {
    [$user, $order, $quote, $deficiency, $product] = adicionalPendiente();
    $this->actingAs($user)->post(route('vendedor.deficiencias.cotizar', [$user->currentTeam, $deficiency]), ['product_id' => $product->id, 'cantidad' => 1]);
    $datos = ['autorizado' => true, 'autorizado_por' => 'Cliente', 'canal' => 'whatsapp', 'fecha' => today()->toDateString()];
    $this->post(route('vendedor.deficiencias.autorizar', [$user->currentTeam, $deficiency]), $datos);
    InventoryMovement::create(['product_id' => $product->id, 'sede_id' => $order->sede_id, 'tipo' => 'ingreso', 'cantidad' => 3, 'user_id' => $user->id]);
    app(ExecuteAndCloseServiceOrder::class)->consumeSparePart($order->refresh(), $deficiency->refresh(), $product, 1, $user);
    $this->put(route('vendedor.cotizaciones.update', [$user->currentTeam, $quote]), ['client_id' => $order->client_id, 'fecha' => today()->toDateString(), 'vigencia_hasta' => today()->addDays(15)->toDateString(), 'items' => [
        ['service_id' => $order->service_id, 'cantidad' => 1, 'precio_unitario' => 50],
        ['deficiency_id' => $deficiency->id, 'product_id' => $product->id, 'cantidad' => 1, 'precio_unitario' => 12],
    ]])->assertSessionHasNoErrors();
    expect($deficiency->refresh()->estado)->toBe('resuelta')->and($deficiency->authorization)->toBeNull();
    $this->post(route('vendedor.deficiencias.autorizar', [$user->currentTeam, $deficiency]), $datos)->assertSessionHasNoErrors();
    expect($deficiency->refresh()->estado)->toBe('resuelta');
    expect(fn () => app(ExecuteAndCloseServiceOrder::class)->consumeSparePart($order->refresh(), $deficiency->refresh(), $product, 1, $user))->toThrow(ValidationException::class);
    expect(InventoryMovement::where('referencia_type', $deficiency->getMorphClass())->where('referencia_id', $deficiency->id)->where('tipo', 'salida_servicio')->sum('cantidad'))->toEqual(-1);
});

test('borrador con detraccion sin clasificar muestra detalle y aviso sin bloquear su correccion', function () {
    $user = vendedorUser();
    $sale = Sale::factory()->create(['vendedor_id' => $user->id, 'estado' => 'borrador', 'comprobante_tipo' => 'factura', 'total' => 900]);
    $service = Service::factory()->create(['nombre' => 'Recarga y mantenimiento combinados', 'codigo_detraccion' => null]);
    SaleItem::factory()->create(['sale_id' => $sale->id, 'service_id' => $service->id, 'product_id' => null, 'tipo_linea' => 'servicio']);
    $this->actingAs($user)->get(route('vendedor.ventas.show', [$user->currentTeam, $sale]))->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->component('vendedor/ventas/show')->where('errorDetraccion', fn ($error) => is_string($error) && str_contains($error, 'clasificación')));
});
