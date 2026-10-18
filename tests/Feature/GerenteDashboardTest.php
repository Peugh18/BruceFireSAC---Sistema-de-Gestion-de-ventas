<?php

use App\Models\Certificate;
use App\Models\CertificateUnit;
use App\Models\Client;
use App\Models\ClientRetentionScore;
use App\Models\ElectronicDocument;
use App\Models\Installment;
use App\Models\Sale;
use App\Models\SalePayment;
use App\Models\Sede;
use App\Models\ServiceOrder;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function createGerenteUserForDashboardTest(): User
{
    $user = User::factory()->create();
    $user->assignRole('Gerente');

    return $user;
}

test('gerente accede al resumen ejecutivo y recibe la tendencia mensual y sedes disponibles', function () {
    $user = createGerenteUserForDashboardTest();

    $this->actingAs($user)
        ->get(route('gerente.dashboard', ['current_team' => $user->currentTeam]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('gerente/dashboard')
            ->has('metrics', fn (Assert $metrics) => $metrics
                ->has('ventasMes')
                ->has('ventasMesAnterior')
                ->has('montoCobrado')
                ->has('montoCobradoMesAnterior')
                ->has('vencidoPorCobrar')
                ->has('cotizacionesPendientes')
                ->has('ordenesEnProceso')
                ->has('stockCritico')
                ->has('documentosSunatError')
                ->has('plazoSunat')
            )
            ->has('metricas', 8)
            ->has('clientesConDeuda')
            ->has('ordenesPorPaso')
            ->has('recompra', 3)
            ->has('graficos.ventas', 6)
            ->has('graficos.conversion', 3)
            ->has('graficos.cobranza', 3)
            ->has('ventasPorVendedor')
            ->has('serviciosDelMes')
            ->has('sedes')
            ->has('periodo')
            ->where('sedeSeleccionada', null)
        );
});

test('el filtro agrupa la tienda con su almacen y limita los indicadores a esa sede', function () {
    $gerente = createGerenteUserForDashboardTest();
    $almacen = Sede::factory()->almacen()->create();
    $tienda = Sede::factory()->tienda()->create(['almacen_id' => $almacen->id]);
    $otroAlmacen = Sede::factory()->almacen()->create();
    $otraSede = Sede::factory()->tienda()->create(['almacen_id' => $otroAlmacen->id]);

    foreach ([[$tienda, 100], [$almacen, 200], [$otraSede, 900]] as [$sede, $total]) {
        Sale::factory()->create([
            'sede_id' => $sede->id,
            'fecha' => today(),
            'total' => $total,
            'estado' => 'confirmada',
        ]);
    }

    $parametros = ['current_team' => $gerente->currentTeam, 'sede' => $tienda->id];

    $this->actingAs($gerente)
        ->get(route('gerente.dashboard', $parametros))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('metrics.ventasMes', 300)
            ->where('graficos.ventas.5.valor', 300)
            ->where('sedeSeleccionada', $tienda->id)
            ->has('sedes', 2)
        );
});

test('dashboard generico redirige a usuarios con rol Gerente a gerente.dashboard', function () {
    $user = createGerenteUserForDashboardTest();

    $this->actingAs($user)
        ->get(route('dashboard', ['current_team' => $user->currentTeam]))
        ->assertRedirect(route('gerente.dashboard', ['current_team' => $user->currentTeam]));
});

test('dashboard de gerente calcula metricas con datos reales', function () {
    $user = createGerenteUserForDashboardTest();
    $client = Client::factory()->create();

    // Venta de hoy
    $sale = Sale::factory()->create([
        'client_id' => $client->id,
        'vendedor_id' => $user->id,
        'fecha' => today(),
        'total' => 1500.00,
        'comprobante_tipo' => 'factura',
        'estado' => 'confirmada',
    ]);

    // Cuota vencida
    Installment::factory()->create([
        'sale_id' => $sale->id,
        'monto' => 500.00,
        'fecha_vencimiento' => today()->subDays(5),
        'estado' => 'pendiente',
    ]);

    // Pago de mes
    SalePayment::factory()->create([
        'sale_id' => $sale->id,
        'monto' => 1000.00,
        'fecha' => today(),
    ]);

    $this->actingAs($user)
        ->get(route('gerente.dashboard', ['current_team' => $user->currentTeam]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('gerente/dashboard')
            ->where('metrics.ventasMes', 1500)
            ->where('metrics.montoCobrado', 1000)
            ->where('metrics.vencidoPorCobrar', 500)
        );
});

test('gerente descuenta solo las notas aceptadas del periodo en ventas y facturacion', function () {
    $this->travelTo(Carbon::parse('2026-10-09 12:00:00'));
    $user = createGerenteUserForDashboardTest();
    $sale = Sale::factory()->create(['fecha' => today(), 'total' => 1500, 'comprobante_tipo' => 'factura', 'estado' => 'confirmada']);
    $previousSale = Sale::factory()->create(['fecha' => '2026-09-10', 'total' => 1000, 'comprobante_tipo' => 'boleta', 'estado' => 'confirmada']);
    $cancelled = Sale::factory()->create(['fecha' => today(), 'total' => 100, 'comprobante_tipo' => 'boleta', 'estado' => 'anulada']);
    ElectronicDocument::factory()->create(['sale_id' => $cancelled->id, 'tipo' => 'nota_credito', 'sunat_estado' => 'aceptado', 'importe' => 100, 'fecha_emision' => today()]);
    foreach ([[$sale, 'aceptado', 200, '2026-10-09'], [$sale, 'observado', 100, '2026-10-09'], [$sale, 'rechazado', 500, '2026-10-09'], [$sale, 'pendiente', 500, '2026-10-09'], [$previousSale, 'aceptado', 50, '2026-10-08']] as [$venta, $estado, $importe, $fecha]) {
        ElectronicDocument::factory()->create(['sale_id' => $venta->id, 'tipo' => 'nota_credito', 'sunat_estado' => $estado, 'importe' => $importe, 'fecha_emision' => $fecha]);
    }
    $this->actingAs($user)->get(route('gerente.dashboard', ['current_team' => $user->currentTeam]))
        ->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('metrics.ventasMes', 1150)
        ->where('metricas.0.valor', 1150));
});

test('la tendencia separa ventas por fecha de venta y cobros recibidos en el mes', function () {
    $this->travelTo(Carbon::parse('2026-10-14 12:00:00'));
    $user = createGerenteUserForDashboardTest();
    $ventaAnterior = Sale::factory()->create([
        'fecha' => '2026-09-20',
        'total' => 300,
        'estado' => 'confirmada',
    ]);
    Sale::factory()->create([
        'fecha' => '2026-10-10',
        'total' => 125,
        'estado' => 'confirmada',
    ]);
    SalePayment::factory()->create([
        'sale_id' => $ventaAnterior->id,
        'monto' => 75,
        'fecha' => '2026-10-12',
    ]);

    $this->actingAs($user)
        ->get(route('gerente.dashboard', ['current_team' => $user->currentTeam]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('metricas.0.clave', 'ventasNetas')
            ->where('metricas.0.anterior', 300)
            ->where('metricas.0.valor', 125)
            ->where('metricas.1.clave', 'cobrado')
            ->where('metricas.1.anterior', 0)
            ->where('metricas.1.valor', 75));
});

test('recompra cuenta extintores por tramo y respeta la sede elegida', function () {
    $gerente = createGerenteUserForDashboardTest();
    $almacen = Sede::factory()->almacen()->create();
    $tienda = Sede::factory()->tienda()->create(['almacen_id' => $almacen->id]);
    $otraAlmacen = Sede::factory()->almacen()->create();
    $otraTienda = Sede::factory()->tienda()->create(['almacen_id' => $otraAlmacen->id]);
    $cliente = Client::factory()->create();
    foreach ([[20, 2, $tienda], [45, 3, $tienda], [75, 4, $tienda], [20, 9, $otraTienda]] as [$dias, $unidades, $sede]) {
        $venta = Sale::factory()->create(['client_id' => $cliente->id, 'sede_id' => $sede->id, 'estado' => 'confirmada']);
        $certificado = Certificate::factory()->create(['client_id' => $cliente->id, 'sale_id' => $venta->id, 'fecha_vigencia_hasta' => today()->addDays($dias)]);
        CertificateUnit::factory()->count($unidades)->create(['certificate_id' => $certificado->id]);
    }

    $this->actingAs($gerente)->get(route('gerente.dashboard', ['current_team' => $gerente->currentTeam, 'sede' => $tienda->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('recompra.0.cantidad', 2)
            ->where('recompra.1.cantidad', 3)
            ->where('recompra.2.cantidad', 4));
});

test('el almacén mixto que surte a una tienda no cuenta como otra sede de venta', function () {
    $gerente = createGerenteUserForDashboardTest();
    $almacenMixto = Sede::factory()->mixta()->create(['nombre' => 'Almacén Central']);
    Sede::factory()->tienda()->create(['nombre' => 'Tienda Centro', 'almacen_id' => $almacenMixto->id]);

    $this->actingAs($gerente)->get(route('gerente.dashboard', ['current_team' => $gerente->currentTeam]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('mostrarComparacionSedes', false)
            ->where('sedes', []));
});

test('el dashboard muestra a quién llamar según la IA de recompra', function () {
    $gerente = createGerenteUserForDashboardTest();
    $cliente = Client::factory()->create(['nombre_comercial' => 'Grifo El Sol']);
    ClientRetentionScore::query()->create([
        'client_id' => $cliente->id, 'probabilidad' => 0.91, 'categoria' => 'alta',
        'recencia_dias' => 3, 'frecuencia_compras' => 8, 'monto_total' => 225, 'ticket_promedio' => 28,
        'antiguedad_dias' => 31, 'diversidad_productos' => 1, 'compro_recarga' => false,
        'factores_json' => ['resumen' => 'Compra seguido.'], 'scored_at' => now(),
    ]);

    $this->actingAs($gerente)->get(route('gerente.dashboard', ['current_team' => $gerente->currentTeam]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('iaRecompra.alta', 1)
            ->where('iaRecompra.clientes.0.cliente', 'Grifo El Sol')
            ->where('iaRecompra.clientes.0.porcentaje', 91));
});

test('cada enlace del resumen abre solo lo que cuenta: paso, tramo o comprobantes vencidos', function () {
    $gerente = createGerenteUserForDashboardTest();
    $equipo = ['current_team' => $gerente->currentTeam];
    ServiceOrder::factory()->create(['estado' => 'pendiente_recepcion']);
    ServiceOrder::factory()->create(['estado' => 'en_proceso']);

    $this->actingAs($gerente)->get(route('gerente.dashboard.detalle', [...$equipo, 'tipo' => 'ordenes', 'paso' => 'recibir']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('titulo', 'Órdenes de servicio en curso')
            ->has('rows', 1)
            ->where('rows.0.estado', 'Pendiente recepcion'));

    $this->get(route('gerente.dashboard.detalle', [...$equipo, 'tipo' => 'sunat_vencidos']))
        ->assertInertia(fn (Assert $page) => $page->where('titulo', 'Comprobantes vencidos en SUNAT'));

    $this->get(route('gerente.dashboard.detalle', [...$equipo, 'tipo' => 'recompra', 'tramo' => 60]))
        ->assertInertia(fn (Assert $page) => $page->where('titulo', 'Extintores que vencen en 60 días'));
});
