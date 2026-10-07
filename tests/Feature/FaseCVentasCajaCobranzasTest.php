<?php

use App\Actions\Billing\AplicarNotaAlSaldo;
use App\Actions\Cash\CloseCashRegister;
use App\Actions\Cash\OpenCashRegister;
use App\Actions\Cotizaciones\CreateQuote;
use App\Actions\Cotizaciones\TransitionQuoteState;
use App\Actions\Sales\ConfirmSale;
use App\Actions\Sales\CreateSale;
use App\Http\Controllers\Gerente\DashboardController;
use App\Models\AlertContact;
use App\Models\Client;
use App\Models\Deficiency;
use App\Models\ElectronicDocument;
use App\Models\Equipment;
use App\Models\Installment;
use App\Models\InventoryUnit;
use App\Models\Product;
use App\Models\Quote;
use App\Models\QuoteItem;
use App\Models\Sale;
use App\Models\SalePayment;
use App\Models\Sede;
use App\Models\Service;
use App\Models\ServiceOrder;
use App\Models\User;
use App\Models\Vehicle;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->vendedor = User::factory()->create();
    $this->vendedor->assignRole('Vendedor');
    $this->team = ['current_team' => $this->vendedor->currentTeam];
});

/**
 * Venta a crédito emitida con una sola cuota y su factura aceptada.
 */
function ventaACreditoConFactura(User $vendedor, float $total = 118): ElectronicDocument
{
    $sale = Sale::factory()->create(['vendedor_id' => $vendedor->id, 'estado' => 'confirmada', 'condicion_pago' => 'credito', 'comprobante_tipo' => 'factura', 'total' => $total]);
    Installment::factory()->create(['sale_id' => $sale->id, 'numero_cuota' => 1, 'monto' => $total, 'estado' => 'pendiente', 'fecha_vencimiento' => today()->addDays(30)]);

    return ElectronicDocument::factory()->create(['sale_id' => $sale->id, 'tipo' => 'factura', 'sunat_estado' => 'aceptado']);
}

test('V1: una linea con serie solo admite cantidad 1', function () {
    $sede = Sede::factory()->almacen()->create();
    $product = Product::factory()->create(['precio_venta' => 100]);
    $unit = InventoryUnit::factory()->create(['product_id' => $product->id, 'sede_almacen_id' => $sede->id, 'estado' => 'disponible']);
    $linea = ['tipo_linea' => 'unidad_nueva', 'numero_serie' => $unit->numero_serie, 'product_id' => $product->id, 'cantidad' => 9, 'precio_unitario' => 100];

    $this->actingAs($this->vendedor)->post(route('vendedor.ventas.store', $this->team), [
        'client_id' => Client::factory()->create()->id, 'sede_id' => $sede->id, 'fecha' => today()->toDateString(),
        'destino' => 'local_cliente', 'condicion_pago' => 'contado', 'medio_pago' => 'yape', 'comprobante_tipo' => 'nota_venta',
        'items' => [$linea],
    ])->assertSessionHasErrors('items.0.cantidad');

    expect(fn () => app(CreateSale::class)->handle(
        ['client_id' => Client::factory()->create()->id, 'sede_id' => $sede->id, 'fecha' => today()->toDateString(), 'destino' => 'local_cliente', 'condicion_pago' => 'contado', 'comprobante_tipo' => 'nota_venta'],
        [$linea],
        $this->vendedor->id,
    ))->toThrow(ValidationException::class)
        ->and($unit->refresh()->estado)->toBe('disponible');
});

test('V3: la cotizacion pasa a la venta con descuento, condicion, observaciones y vehiculo', function () {
    $client = Client::factory()->create();
    $vehicle = Vehicle::factory()->create(['client_id' => $client->id]);
    $quote = Quote::factory()->aceptada()->create(['client_id' => $client->id, 'vehicle_id' => $vehicle->id, 'condicion_pago_propuesta' => 'Crédito 30 días', 'observaciones' => 'Entregar en almacén', 'referencia' => 'PLACA: ABC-123']);
    QuoteItem::factory()->create(['quote_id' => $quote->id, 'cantidad' => 2, 'precio_unitario' => 100, 'descuento' => 20]);

    $this->actingAs($this->vendedor)
        ->get(route('vendedor.ventas.create', [...$this->team, 'cotizacion' => $quote->id]))
        ->assertInertia(fn ($page) => $page
            ->where('quote.items.0.descuento', 20)
            ->where('quote.condicion_pago', 'credito')
            ->where('quote.observaciones', 'Entregar en almacén')
            ->where('quote.vehicle_id', $vehicle->id)
            ->where('quote.destino', 'vehiculo')
            ->where('quote.referencia', 'PLACA: ABC-123'));
});

test('V4: una NC parcial aceptada rebaja el saldo y una ND agrega una cuota, una sola vez', function () {
    $factura = ventaACreditoConFactura($this->vendedor);
    $sale = $factura->sale;

    $nc = ElectronicDocument::factory()->create(['sale_id' => $sale->id, 'tipo' => 'nota_credito', 'cpe_afectado_id' => $factura->id, 'motivo_catalogo' => '04', 'importe' => 59, 'sunat_estado' => 'aceptado']);
    app(AplicarNotaAlSaldo::class)->handle($nc);
    app(AplicarNotaAlSaldo::class)->handle($nc->refresh());

    $cuota = $sale->installments()->first();
    expect((float) $cuota->monto)->toBe(118.0)
        ->and((float) $cuota->monto_acreditado)->toBe(59.0)
        ->and($cuota->saldo())->toBe(59.0);

    $nd = ElectronicDocument::factory()->create(['sale_id' => $sale->id, 'tipo' => 'nota_debito', 'cpe_afectado_id' => $factura->id, 'motivo_catalogo' => '03', 'importe' => 20, 'sunat_estado' => 'aceptado']);
    app(AplicarNotaAlSaldo::class)->handle($nd);

    $nueva = $sale->installments()->where('electronic_document_id', $nd->id)->first();
    expect($nueva)->not->toBeNull()
        ->and((float) $nueva->monto)->toBe(20.0)
        ->and($sale->installments()->count())->toBe(2)
        ->and((float) $sale->installments()->get()->sum(fn (Installment $i) => $i->saldo()))->toBe(79.0);

    // Una nota rechazada no cambia nada.
    $rechazada = ElectronicDocument::factory()->create(['sale_id' => $sale->id, 'tipo' => 'nota_debito', 'cpe_afectado_id' => $factura->id, 'importe' => 50, 'sunat_estado' => 'rechazado']);
    app(AplicarNotaAlSaldo::class)->handle($rechazada);
    expect($sale->installments()->count())->toBe(2);
});

test('V6: autorizar un adicional con la orden cobrada crea una cuota por cobrar', function () {
    $sale = Sale::factory()->create(['vendedor_id' => $this->vendedor->id, 'estado' => 'confirmada', 'condicion_pago' => 'contado']);
    $order = ServiceOrder::factory()->create(['client_id' => $sale->client_id, 'sale_id' => $sale->id]);
    $deficiency = Deficiency::factory()->create(['service_order_id' => $order->id]);
    $datos = ['autorizado' => true, 'autorizado_por' => 'Ana', 'canal' => 'whatsapp', 'fecha' => today()->toDateString()];

    $this->actingAs($this->vendedor)
        ->post(route('vendedor.deficiencias.autorizar', [...$this->team, 'deficiency' => $deficiency]), $datos)
        ->assertSessionHasErrors('importe');

    $this->actingAs($this->vendedor)
        ->post(route('vendedor.deficiencias.autorizar', [...$this->team, 'deficiency' => $deficiency]), [...$datos, 'importe' => 45.5])
        ->assertSessionHasNoErrors();

    $cuota = $sale->installments()->first();
    expect($cuota)->not->toBeNull()
        ->and((float) $cuota->monto)->toBe(45.5)
        ->and($cuota->deficiency_authorization_id)->toBe($deficiency->authorization->id)
        ->and($deficiency->refresh()->estado)->toBe('autorizada');
});

test('V7: el cobro queda en su turno y anularlo despues no cambia el turno cerrado', function () {
    $turno = app(OpenCashRegister::class)->handle($this->vendedor, null, 0);
    $sale = Sale::factory()->create(['vendedor_id' => $this->vendedor->id, 'estado' => 'confirmada']);
    $cuota = Installment::factory()->create(['sale_id' => $sale->id, 'monto' => 100]);
    $pago = SalePayment::factory()->create(['sale_id' => $sale->id, 'installment_id' => $cuota->id, 'forma_pago' => 'efectivo', 'monto' => 100]);

    expect($pago->cash_register_id)->toBe($turno->id)
        ->and(fn () => app(OpenCashRegister::class)->handle($this->vendedor, null, 0))->toThrow(ValidationException::class);

    app(CloseCashRegister::class)->handle($turno, 100);

    // Sin turno abierto no se devuelve efectivo de un turno cerrado.
    expect(fn () => $pago->anular('Error', $this->vendedor->id))->toThrow(ValidationException::class);

    $nuevo = app(OpenCashRegister::class)->handle($this->vendedor, null, 0);
    $pago->anular('Cobro duplicado', $this->vendedor->id);

    expect($turno->refresh()->movimientosPorFormaDePago()['efectivo'])->toBe(100.0)
        ->and($nuevo->movimientosPorFormaDePago()['efectivo'])->toBe(-100.0)
        ->and(SalePayment::withTrashed()->find($pago->id)->anulacion_cash_register_id)->toBe($nuevo->id);
});

test('V7: una venta no se confirma dos veces y la numeracion no usa max id', function () {
    $sale = Sale::factory()->create(['vendedor_id' => $this->vendedor->id, 'estado' => 'borrador', 'comprobante_tipo' => 'nota_venta', 'condicion_pago' => 'credito']);
    $copia = Sale::query()->findOrFail($sale->id);

    app(ConfirmSale::class)->handle($sale);

    // La segunda solicitud traía la venta aún en borrador.
    expect($copia->estado)->toBe('borrador')
        ->and(fn () => app(ConfirmSale::class)->handle($copia))->toThrow(ValidationException::class);

    $datos = ['client_id' => Client::factory()->create()->id, 'fecha' => today(), 'vigencia_hasta' => today()->addDays(15)];
    $item = [['service_id' => Service::factory()->create()->id, 'cantidad' => 1, 'precio_unitario' => 10]];
    $primera = app(CreateQuote::class)->handle($datos, $item, $this->vendedor->id);
    Quote::factory()->create(['numero' => 'COT-'.str_pad((string) ((int) substr($primera->numero, 4) + 1), 4, '0', STR_PAD_LEFT)]);
    $segunda = app(CreateQuote::class)->handle($datos, $item, $this->vendedor->id);

    expect((int) substr($segunda->numero, 4))->toBe((int) substr($primera->numero, 4) + 2);
});

test('X6: la tarea diaria no vence una cotizacion aceptada', function () {
    $aceptada = Quote::factory()->aceptada()->create(['vigencia_hasta' => today()->subDay()]);
    $emitida = Quote::factory()->create(['estado' => 'emitida', 'vigencia_hasta' => today()->subDay()]);

    $this->artisan('quotes:expire')->assertSuccessful();

    expect($aceptada->refresh()->estado)->toBe('aceptada')
        ->and($emitida->refresh()->estado)->toBe('vencida');
});

test('X7: mide el registro de venta, el tiempo de cotizacion y los clientes recuperados', function () {
    $this->travelTo(now()->startOfMonth()->addDays(10)->setTime(10, 0));
    $client = Client::factory()->create();
    $equipment = Equipment::factory()->create(['client_id' => $client->id]);
    $service = Service::factory()->create();

    $quote = app(CreateQuote::class)->handle(['client_id' => $client->id, 'fecha' => today(), 'vigencia_hasta' => today()->addDays(15), 'origen_alerta_equipment_id' => $equipment->id], [['service_id' => $service->id, 'cantidad' => 1, 'precio_unitario' => 50]], $this->vendedor->id);
    $this->travel(30)->minutes();
    app(TransitionQuoteState::class)->handle($quote, 'emitida');

    $sale = app(CreateSale::class)->handle(
        ['client_id' => $client->id, 'quote_id' => $quote->id, 'fecha' => today()->toDateString(), 'destino' => 'local_cliente', 'condicion_pago' => 'credito', 'comprobante_tipo' => 'nota_venta', 'iniciado_at' => now()->subMinutes(5)->toIso8601String()],
        [['tipo_linea' => 'servicio', 'service_id' => $service->id, 'cantidad' => 1, 'precio_unitario' => 50]],
        $this->vendedor->id,
    );
    app(ConfirmSale::class)->handle($sale);

    $kpis = DashboardController::kpisDelProyecto(now()->startOfMonth(), now()->endOfMonth());

    expect($kpis['venta_minutos'])->toBe(5.0)
        ->and($kpis['cotizacion_minutos'])->toBe(30.0)
        ->and($kpis['clientes_recuperados'])->toBe(1);
});

test('X8 y X9: contactado queda registrado y la recarga se elige por agente y capacidad', function () {
    $client = Client::factory()->create();
    $product = Product::factory()->create(['agente' => 'co2', 'capacidad' => '6 kg']);
    $equipment = Equipment::factory()->create(['client_id' => $client->id, 'product_id' => $product->id, 'tipo_agente' => 'CO2', 'capacidad' => '6 KG']);
    Service::factory()->create(['nombre' => 'Recarga PQS 6 kg', 'agente' => 'pqs', 'capacidad' => '6 kg', 'activo' => true]);

    $ofrecer = fn () => $this->actingAs($this->vendedor)->post(route('vendedor.alertas.ofrecer-recarga', $this->team), ['client_id' => $client->id, 'equipment_ids' => [$equipment->id]]);

    $ofrecer()->assertSessionHasErrors('service');

    $co2 = Service::factory()->create(['nombre' => 'Servicio CO2', 'agente' => 'co2', 'capacidad' => '6kg', 'activo' => true, 'precio_venta' => 70]);
    $ofrecer()->assertSessionHasNoErrors();

    $quote = Quote::query()->latest('id')->firstOrFail();
    expect($quote->items()->first()->service_id)->toBe($co2->id)
        ->and($quote->origen_alerta_equipment_id)->toBe($equipment->id);

    $this->actingAs($this->vendedor)
        ->post(route('vendedor.alertas.contactado', $this->team), ['client_id' => $client->id, 'equipment_id' => $equipment->id, 'nota' => 'Llamar en enero'])
        ->assertSessionHasNoErrors();

    expect(AlertContact::ultimosPorCliente([$client->id])->get($client->id))
        ->toMatchArray(['nota' => 'Llamar en enero', 'usuario' => $this->vendedor->name]);
});

test('X8: un vendedor de otra sede no marca como contactado un equipo ajeno', function () {
    $sedeA = Sede::factory()->create();
    $sedeB = Sede::factory()->create();
    $this->vendedor->update(['sede_id' => $sedeA->id]);
    $ajeno = Equipment::factory()->create();
    Sale::factory()->create(['sede_id' => $sedeB->id, 'client_id' => $ajeno->client_id])
        ->items()->create(['tipo_linea' => 'recarga_servicio', 'equipment_id' => $ajeno->id, 'cantidad' => 1, 'precio_unitario' => 10, 'descuento' => 0, 'subtotal' => 10]);

    $this->actingAs($this->vendedor)
        ->post(route('vendedor.alertas.contactado', $this->team), ['client_id' => $ajeno->client_id, 'equipment_id' => $ajeno->id])
        ->assertNotFound();

    expect(AlertContact::count())->toBe(0);
});

test('X8: el vendedor solo ve las alertas de su sede', function () {
    $sedeA = Sede::factory()->create();
    $sedeB = Sede::factory()->create();
    $this->vendedor->update(['sede_id' => $sedeA->id]);
    $deOtraSede = Equipment::factory()->create(['proxima_fecha_atencion' => today()->subDay()]);
    Sale::factory()->create(['sede_id' => $sedeB->id, 'client_id' => $deOtraSede->client_id])
        ->items()->create(['tipo_linea' => 'recarga_servicio', 'equipment_id' => $deOtraSede->id, 'cantidad' => 1, 'precio_unitario' => 10, 'descuento' => 0, 'subtotal' => 10]);

    $this->actingAs($this->vendedor)
        ->get(route('vendedor.alertas.index', $this->team))
        ->assertInertia(fn ($page) => $page->where('alerts.vencidas', fn ($alertas) => collect($alertas)->where('equipment_id', $deOtraSede->id)->isEmpty()));
});
