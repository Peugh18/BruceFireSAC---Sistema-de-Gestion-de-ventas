<?php

use App\Models\Client;
use App\Models\Equipment;
use App\Models\Quote;
use App\Models\QuoteItem;
use App\Models\Sede;
use App\Models\Service;
use App\Models\ServiceOrder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function servicioOrdenLinea(string $nombre, string $agente, string $capacidad, float $precio = 80): Service
{
    return Service::factory()->create([
        'nombre' => $nombre,
        'agente' => $agente,
        'capacidad' => $capacidad,
        'precio_venta' => $precio,
    ]);
}

test('orden desde cotizacion con dos servicios crea dos lineas', function () {
    $user = vendedorUser();
    $client = Client::factory()->create();
    $quote = Quote::factory()->create(['client_id' => $client->id, 'vendedor_id' => $user->id, 'estado' => 'emitida']);
    $co2 = servicioOrdenLinea('Recarga CO2 5 lb', 'co2', '5 lb');
    $pqs = servicioOrdenLinea('Recarga PQS 6 kg', 'pqs', '6 kg');
    $itemCo2 = QuoteItem::factory()->forService($co2)->create(['quote_id' => $quote->id, 'cantidad' => 4]);
    $itemPqs = QuoteItem::factory()->forService($pqs)->create(['quote_id' => $quote->id, 'cantidad' => 2]);

    $this->actingAs($user)
        ->post(route('vendedor.cotizaciones.orden-taller', ['current_team' => $user->currentTeam, 'quote' => $quote]))
        ->assertSessionHasNoErrors();

    $order = ServiceOrder::where('quote_id', $quote->id)->sole();

    expect($order->service_id)->toBe($co2->id)
        ->and(DB::table('service_order_lines')->where('service_order_id', $order->id)->orderBy('id')->get(['service_id', 'cantidad', 'quote_item_id', 'adicional'])->map(fn ($line) => [
            'service_id' => (int) $line->service_id,
            'cantidad' => (int) $line->cantidad,
            'quote_item_id' => (int) $line->quote_item_id,
            'adicional' => (bool) $line->adicional,
        ])->all())->toBe([
            ['service_id' => $co2->id, 'cantidad' => 4, 'quote_item_id' => $itemCo2->id, 'adicional' => false],
            ['service_id' => $pqs->id, 'cantidad' => 2, 'quote_item_id' => $itemPqs->id, 'adicional' => false],
        ]);
});

test('un pqs seis kilos entra en la linea pqs aunque el servicio principal sea co2', function () {
    $user = vendedorUser();
    $client = Client::factory()->create();
    $co2 = servicioOrdenLinea('Recarga CO2 5 lb', 'co2', '5 lb');
    $pqs = servicioOrdenLinea('Recarga PQS 6 kg', 'pqs', '6 kg');
    $order = ServiceOrder::factory()->create(['client_id' => $client->id, 'service_id' => $co2->id]);
    $lineCo2 = DB::table('service_order_lines')->insertGetId(['service_order_id' => $order->id, 'service_id' => $co2->id, 'cantidad' => 1, 'created_at' => now(), 'updated_at' => now()]);
    $linePqs = DB::table('service_order_lines')->insertGetId(['service_order_id' => $order->id, 'service_id' => $pqs->id, 'cantidad' => 1, 'created_at' => now(), 'updated_at' => now()]);
    $equipment = Equipment::factory()->create(['client_id' => $client->id, 'numero_serie' => 'BF-EQ-PQS001', 'tipo_agente' => 'PQS ABC', 'capacidad' => '6 kg']);

    $this->actingAs($user)
        ->post(route('vendedor.ordenes-servicio.equipos.store', ['current_team' => $user->currentTeam, 'service_order' => $order]), [
            'numero_serie' => 'BF-EQ-PQS001',
        ])
        ->assertSessionHasNoErrors();

    $pivot = DB::table('service_order_equipment')->where('service_order_id', $order->id)->where('equipment_id', $equipment->id)->first();

    expect((int) $pivot->service_order_line_id)->toBe($linePqs)
        ->and((int) $pivot->service_order_line_id)->not->toBe($lineCo2);
});

test('escanear un bf-eq existente del cliente lo coloca solo en su casillero compatible', function () {
    $user = vendedorUser();
    $client = Client::factory()->create();
    $co2 = servicioOrdenLinea('Recarga CO2 5 lb', 'co2', '5 lb');
    $pqs = servicioOrdenLinea('Recarga PQS 6 kg', 'pqs', '6 kg');
    $order = ServiceOrder::factory()->create(['client_id' => $client->id, 'service_id' => $co2->id]);
    $lineCo2 = DB::table('service_order_lines')->insertGetId(['service_order_id' => $order->id, 'service_id' => $co2->id, 'cantidad' => 1, 'created_at' => now(), 'updated_at' => now()]);
    $linePqs = DB::table('service_order_lines')->insertGetId(['service_order_id' => $order->id, 'service_id' => $pqs->id, 'cantidad' => 1, 'created_at' => now(), 'updated_at' => now()]);
    $co2Equipment = Equipment::factory()->create(['client_id' => $client->id, 'numero_serie' => 'BF-EQ-CO2001', 'tipo_agente' => 'CO2', 'capacidad' => '5 lb']);
    $pqsEquipment = Equipment::factory()->create(['client_id' => $client->id, 'numero_serie' => 'BF-EQ-PQS002', 'tipo_agente' => 'PQS ABC', 'capacidad' => '6 kg']);

    $this->actingAs($user)->post(route('vendedor.ordenes-servicio.equipos.store', ['current_team' => $user->currentTeam, 'service_order' => $order]), ['numero_serie' => $pqsEquipment->numero_serie])->assertSessionHasNoErrors();
    $this->actingAs($user)->post(route('vendedor.ordenes-servicio.equipos.store', ['current_team' => $user->currentTeam, 'service_order' => $order]), ['numero_serie' => $co2Equipment->numero_serie])->assertSessionHasNoErrors();

    expect((int) DB::table('service_order_equipment')->where('equipment_id', $pqsEquipment->id)->value('service_order_line_id'))->toBe($linePqs)
        ->and((int) DB::table('service_order_equipment')->where('equipment_id', $co2Equipment->id)->value('service_order_line_id'))->toBe($lineCo2);
});

test('un extintor de otro cliente es rechazado', function () {
    $user = vendedorUser();
    $service = servicioOrdenLinea('Recarga PQS 6 kg', 'pqs', '6 kg');
    $order = ServiceOrder::factory()->create(['client_id' => Client::factory()->create()->id, 'service_id' => $service->id]);
    DB::table('service_order_lines')->insert(['service_order_id' => $order->id, 'service_id' => $service->id, 'cantidad' => 1, 'created_at' => now(), 'updated_at' => now()]);
    Equipment::factory()->create(['client_id' => Client::factory()->create()->id, 'numero_serie' => 'BF-EQ-AJENO', 'tipo_agente' => 'PQS ABC', 'capacidad' => '6 kg']);

    $this->actingAs($user)
        ->post(route('vendedor.ordenes-servicio.equipos.store', ['current_team' => $user->currentTeam, 'service_order' => $order]), ['numero_serie' => 'BF-EQ-AJENO'])
        ->assertSessionHasErrors('numero_serie');
});

test('extintor no cotizado crea una linea adicional', function () {
    $user = vendedorUser();
    $client = Client::factory()->create();
    $co2 = servicioOrdenLinea('Recarga CO2 5 lb', 'co2', '5 lb');
    $pqs = servicioOrdenLinea('Recarga PQS 6 kg', 'pqs', '6 kg');
    $order = ServiceOrder::factory()->create(['client_id' => $client->id, 'service_id' => $co2->id]);
    DB::table('service_order_lines')->insert(['service_order_id' => $order->id, 'service_id' => $co2->id, 'cantidad' => 1, 'created_at' => now(), 'updated_at' => now()]);

    $this->actingAs($user)
        ->post(route('vendedor.ordenes-servicio.equipos.store', ['current_team' => $user->currentTeam, 'service_order' => $order]), [
            'service_id' => $pqs->id,
            'adicional' => true,
            'marca' => 'Buckeye',
            'serie_fabricante' => 'FAB-EXTRA-1',
            'anio_fabricacion' => '2024',
        ])
        ->assertSessionHasNoErrors();

    $line = DB::table('service_order_lines')->where('service_order_id', $order->id)->where('adicional', true)->sole();

    expect((int) $line->service_id)->toBe($pqs->id)
        ->and((int) $line->cantidad)->toBe(1)
        ->and(DB::table('service_order_equipment')->where('service_order_line_id', $line->id)->exists())->toBeTrue();
});

test('la venta desde la orden incluye todas las lineas', function () {
    $user = vendedorUser();
    $client = Client::factory()->create();
    $sede = Sede::factory()->almacen()->create();
    $co2 = servicioOrdenLinea('Recarga CO2 5 lb', 'co2', '5 lb', 90);
    $pqs = servicioOrdenLinea('Recarga PQS 6 kg', 'pqs', '6 kg', 70);
    $order = ServiceOrder::factory()->create(['client_id' => $client->id, 'sede_id' => $sede->id, 'service_id' => $co2->id]);
    DB::table('service_order_lines')->insert([
        ['service_order_id' => $order->id, 'service_id' => $co2->id, 'cantidad' => 2, 'adicional' => false, 'created_at' => now(), 'updated_at' => now()],
        ['service_order_id' => $order->id, 'service_id' => $pqs->id, 'cantidad' => 3, 'adicional' => true, 'created_at' => now(), 'updated_at' => now()],
    ]);

    $this->actingAs($user)
        ->get(route('vendedor.ventas.create', ['current_team' => $user->currentTeam, 'orden_servicio' => $order->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('venta.items', 2)
            ->where('venta.items.0.service_id', $co2->id)
            ->where('venta.items.0.cantidad', 2)
            ->where('venta.items.1.service_id', $pqs->id)
            ->where('venta.items.1.cantidad', 3)
        );
});

test('reordenar extintores cambia su posicion', function () {
    $user = vendedorUser();
    $client = Client::factory()->create();
    $service = servicioOrdenLinea('Recarga PQS 6 kg', 'pqs', '6 kg');
    $order = ServiceOrder::factory()->create(['client_id' => $client->id, 'service_id' => $service->id]);
    $line = DB::table('service_order_lines')->insertGetId(['service_order_id' => $order->id, 'service_id' => $service->id, 'cantidad' => 2, 'created_at' => now(), 'updated_at' => now()]);
    $first = Equipment::factory()->create(['client_id' => $client->id, 'tipo_agente' => 'PQS ABC', 'capacidad' => '6 kg']);
    $second = Equipment::factory()->create(['client_id' => $client->id, 'tipo_agente' => 'PQS ABC', 'capacidad' => '6 kg']);
    $order->equipments()->attach($first->id, ['service_order_line_id' => $line, 'posicion' => 1]);
    $order->equipments()->attach($second->id, ['service_order_line_id' => $line, 'posicion' => 2]);

    $this->actingAs($user)
        ->patch(route('vendedor.ordenes-servicio.equipos.reordenar', ['current_team' => $user->currentTeam, 'service_order' => $order]), [
            'equipos' => [$second->id, $first->id],
        ])
        ->assertSessionHasNoErrors();

    expect((int) DB::table('service_order_equipment')->where('equipment_id', $second->id)->value('posicion'))->toBe(1)
        ->and((int) DB::table('service_order_equipment')->where('equipment_id', $first->id)->value('posicion'))->toBe(2);
});
