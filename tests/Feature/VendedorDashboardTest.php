<?php

use App\Models\CashRegister;
use App\Models\Installment;
use App\Models\Quote;
use App\Models\Sale;
use App\Models\SalePayment;
use App\Models\Sede;
use App\Models\ServiceOrder;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

if (! function_exists('vendedorUser')) {
    function vendedorUser(): User
    {
        $user = User::factory()->create();
        $user->assignRole('Vendedor');

        return $user;
    }
}

test('ventas_hoy solo suma las ventas de HOY del vendedor autenticado', function () {
    $vendedor = vendedorUser();
    $otroVendedor = vendedorUser();

    // Venta de HOY del vendedor autenticado: debe contar (total: 100)
    Sale::factory()->create([
        'vendedor_id' => $vendedor->id,
        'fecha' => today(),
        'total' => 100.00,
    ]);

    // Segunda venta de HOY del vendedor autenticado: debe contar (total: 200)
    Sale::factory()->create([
        'vendedor_id' => $vendedor->id,
        'fecha' => today(),
        'total' => 200.00,
    ]);

    // Venta de AYER del vendedor autenticado: NO debe contar
    Sale::factory()->create([
        'vendedor_id' => $vendedor->id,
        'fecha' => today()->subDay(),
        'total' => 150.00,
    ]);

    // Venta de HOY de OTRO vendedor: NO debe contar
    Sale::factory()->create([
        'vendedor_id' => $otroVendedor->id,
        'fecha' => today(),
        'total' => 500.00,
    ]);

    $response = $this
        ->actingAs($vendedor)
        ->get(route('vendedor.dashboard', ['current_team' => $vendedor->currentTeam]));

    $response->assertOk();

    $ventasHoy = $response->viewData('page')['props']['ventas_hoy'];
    expect($ventasHoy['total'])->toEqual(300.00)
        ->and($ventasHoy['count'])->toBe(2)
        ->and($ventasHoy['ticket_promedio'])->toEqual(150.00);
});

test('la respuesta del dashboard NUNCA incluye acumulados mensuales de la empresa ni datos de otros vendedores', function () {
    $vendedor = vendedorUser();
    $otroVendedor = vendedorUser();

    // Ventas de otros vendedores este mes
    Sale::factory()->create([
        'vendedor_id' => $otroVendedor->id,
        'fecha' => today(),
        'total' => 9999.00,
    ]);

    $response = $this
        ->actingAs($vendedor)
        ->get(route('vendedor.dashboard', ['current_team' => $vendedor->currentTeam]));

    $response->assertOk();

    $props = $response->viewData('page')['props'];

    // Regla dura: NO debe exponer acumulados mensuales de la empresa
    expect($props)->not->toHaveKey('total_mes')
        ->and($props)->not->toHaveKey('ventas_mes')
        ->and($props)->not->toHaveKey('acumulado_empresa')
        ->and($props)->not->toHaveKey('empresa_mes')
        ->and($props)->not->toHaveKey('ventas_totales');

    // Verificar que los datos de otros vendedores no se hayan filtrado en ventas_hoy
    expect($props['ventas_hoy']['total'])->toEqual(0.0)
        ->and($props['ventas_hoy']['count'])->toBe(0);
});

test('caja_hoy es null cuando no hay turno abierto y devuelve métricas cuando sí está abierto', function () {
    $vendedor = vendedorUser();

    // 1. Sin turno abierto
    $responseSinTurno = $this
        ->actingAs($vendedor)
        ->get(route('vendedor.dashboard', ['current_team' => $vendedor->currentTeam]));

    $responseSinTurno->assertOk();
    expect($responseSinTurno->viewData('page')['props']['caja_hoy'])->toBeNull();

    // 2. Con turno abierto
    CashRegister::factory()->create([
        'vendedor_id' => $vendedor->id,
        'fecha_apertura' => now()->subHours(2),
        'monto_apertura' => 100.00,
        'estado' => 'abierto',
    ]);

    $sale = Sale::factory()->create([
        'vendedor_id' => $vendedor->id,
        'fecha' => today(),
    ]);

    SalePayment::factory()->create([
        'sale_id' => $sale->id,
        'forma_pago' => 'efectivo',
        'monto' => 50.00,
        'fecha' => today(),
        'created_at' => now()->subHour(),
    ]);

    SalePayment::factory()->create([
        'sale_id' => $sale->id,
        'forma_pago' => 'yape',
        'monto' => 30.00,
        'fecha' => today(),
        'created_at' => now()->subHour(),
    ]);

    $responseConTurno = $this
        ->actingAs($vendedor)
        ->get(route('vendedor.dashboard', ['current_team' => $vendedor->currentTeam]));

    $responseConTurno->assertOk();
    $cajaHoy = $responseConTurno->viewData('page')['props']['caja_hoy'];

    expect($cajaHoy)->not->toBeNull()
        ->and($cajaHoy['estado'])->toBe('abierto')
        ->and($cajaHoy['monto_apertura'])->toEqual(100.00)
        ->and($cajaHoy['total_efectivo'])->toEqual(50.00)
        ->and($cajaHoy['total_esperado_corriente'])->toEqual(150.00)
        ->and($cajaHoy['por_forma_pago']['efectivo'])->toEqual(50.00)
        ->and($cajaHoy['por_forma_pago']['tarjeta_yape'])->toEqual(30.00);
});

test('cobros_pendientes solo incluye installments de ventas del vendedor autenticado', function () {
    $vendedor = vendedorUser();
    $otroVendedor = vendedorUser();

    $saleVendedor = Sale::factory()->create(['vendedor_id' => $vendedor->id]);
    $saleOtro = Sale::factory()->create(['vendedor_id' => $otroVendedor->id]);

    $installmentMio = Installment::factory()->create([
        'sale_id' => $saleVendedor->id,
        'monto' => 200.00,
        'estado' => 'pendiente',
        'fecha_vencimiento' => today()->addDays(5),
    ]);

    $installmentOtro = Installment::factory()->create([
        'sale_id' => $saleOtro->id,
        'monto' => 800.00,
        'estado' => 'pendiente',
        'fecha_vencimiento' => today()->addDays(2),
    ]);

    $response = $this
        ->actingAs($vendedor)
        ->get(route('vendedor.dashboard', ['current_team' => $vendedor->currentTeam]));

    $response->assertOk();

    $cobros = $response->viewData('page')['props']['cobros_pendientes'];
    $ids = collect($cobros)->pluck('id')->all();

    expect($ids)->toContain($installmentMio->id)
        ->and($ids)->not->toContain($installmentOtro->id);
});

test('cotizaciones_mes solo cuenta por estado para el vendedor autenticado sin montos acumulados', function () {
    $vendedor = vendedorUser();
    $otroVendedor = vendedorUser();

    Quote::factory()->create([
        'vendedor_id' => $vendedor->id,
        'fecha' => today(),
        'estado' => 'borrador',
        'total' => 1200.00,
    ]);

    Quote::factory()->create([
        'vendedor_id' => $vendedor->id,
        'fecha' => today(),
        'estado' => 'enviada',
        'total' => 2500.00,
    ]);

    Quote::factory()->create([
        'vendedor_id' => $otroVendedor->id,
        'fecha' => today(),
        'estado' => 'borrador',
        'total' => 5000.00,
    ]);

    $response = $this
        ->actingAs($vendedor)
        ->get(route('vendedor.dashboard', ['current_team' => $vendedor->currentTeam]));

    $response->assertOk();

    $cotizaciones = $response->viewData('page')['props']['cotizaciones_mes'];

    expect($cotizaciones['borrador'])->toBe(1)
        ->and($cotizaciones['enviada'])->toBe(1)
        ->and($cotizaciones)->not->toHaveKey('total');

    expect($response->viewData('page')['props']['alertas_top'])->toBeArray()->toBeEmpty()
        ->and($response->viewData('page')['props']['agenda_hoy'])->toBeArray()->toBeEmpty();
});

test('agenda_hoy muestra solo las ordenes abiertas de hoy de la sede del vendedor', function () {
    $sedeDelVendedor = Sede::factory()->create();
    $otraSede = Sede::factory()->create();
    $vendedor = vendedorUser();
    $vendedor->update(['sede_id' => $sedeDelVendedor->id]);

    $ordenDeHoy = ServiceOrder::factory()->create([
        'sede_id' => $sedeDelVendedor->id,
        'fecha' => today(),
        'estado' => 'pendiente_recepcion',
    ]);

    $ordenDeOtraSede = ServiceOrder::factory()->create([
        'sede_id' => $otraSede->id,
        'fecha' => today(),
        'estado' => 'pendiente_recepcion',
    ]);

    $ordenDeOtroDia = ServiceOrder::factory()->create([
        'sede_id' => $sedeDelVendedor->id,
        'fecha' => today()->addDay(),
        'estado' => 'pendiente_recepcion',
    ]);

    $response = $this
        ->actingAs($vendedor)
        ->get(route('vendedor.dashboard', ['current_team' => $vendedor->currentTeam]));

    $response->assertOk();

    $agenda = collect($response->viewData('page')['props']['agenda_hoy']);

    expect($agenda->pluck('id')->all())
        ->toBe([$ordenDeHoy->id])
        ->not->toContain($ordenDeOtraSede->id, $ordenDeOtroDia->id)
        ->and($agenda->first())
        ->toMatchArray([
            'codigo' => $ordenDeHoy->codigo,
            'cliente' => $ordenDeHoy->client->razon_social,
            'tipo_servicio' => $ordenDeHoy->tipo_servicio,
            'estado' => $ordenDeHoy->estado,
            'prioridad' => $ordenDeHoy->prioridad,
        ]);
});
