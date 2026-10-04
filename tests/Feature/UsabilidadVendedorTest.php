<?php

use App\Models\Client;
use App\Models\ElectronicDocument;
use App\Models\Installment;
use App\Models\Sale;
use App\Models\Sede;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Código de una pantalla con los espacios y saltos de línea juntados, para que
 * las pruebas no dependan de cómo el formateador parte las líneas.
 */
function codigoDePantalla(string $ruta): string
{
    return (string) preg_replace('/\s+/', ' ', (string) file_get_contents(resource_path($ruta)));
}

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('porcentaje ruc activo y habido en clientes se calcula solo sobre clientes con ruc', function () {
    $user = User::factory()->create();
    $user->assignRole('Vendedor');

    // 2 clientes DNI sin datos SUNAT
    Client::factory()->create([
        'tipo_documento' => 'dni',
        'numero_documento' => '10000001',
        'estado_contribuyente' => null,
        'condicion_domicilio' => null,
    ]);
    Client::factory()->create([
        'tipo_documento' => 'dni',
        'numero_documento' => '10000002',
        'estado_contribuyente' => null,
        'condicion_domicilio' => null,
    ]);

    // 1 cliente RUC activo y habido
    Client::factory()->create([
        'tipo_documento' => 'ruc',
        'numero_documento' => '20100000001',
        'estado_contribuyente' => 'ACTIVO',
        'condicion_domicilio' => 'HABIDO',
    ]);

    // 1 cliente RUC no habido
    Client::factory()->create([
        'tipo_documento' => 'ruc',
        'numero_documento' => '20100000002',
        'estado_contribuyente' => 'ACTIVO',
        'condicion_domicilio' => 'NO HABIDO',
    ]);

    $this->actingAs($user)
        ->get(route('vendedor.clientes.index', ['current_team' => $user->currentTeam]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('vendedor/clientes/index')
            ->where('kpis.porcentaje_activo_habido', fn ($v) => (float) $v === 50.0) // 1 de 2 RUCs = 50%, no 1 de 4 clientes = 25%
        );
});

test('el dashboard incluye el telefono del cliente en cobros_pendientes para whatsapp', function () {
    $user = User::factory()->create();
    $user->assignRole('Vendedor');
    $sede = Sede::factory()->almacen()->create();

    $client = Client::factory()->create([
        'razon_social' => 'CLIENTE TEST S.A.C.',
        'whatsapp' => '987654321',
    ]);

    $sale = Sale::factory()->create([
        'estado' => 'confirmada',
        'vendedor_id' => $user->id,
        'client_id' => $client->id,
        'sede_id' => $sede->id,
    ]);

    $installment = Installment::factory()->create([
        'sale_id' => $sale->id,
        'numero_cuota' => 1,
        'monto' => 350.00,
        'fecha_vencimiento' => now()->subDays(3)->toDateString(),
        'estado' => 'vencido',
    ]);

    $this->actingAs($user)
        ->get(route('vendedor.dashboard', ['current_team' => $user->currentTeam]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('vendedor/dashboard')
            ->has('cobros_pendientes', 1, fn (Assert $item) => $item
                ->where('id', $installment->id)
                ->where('telefono', '987654321')
                ->etc()
            )
        );
});

test('el listado de facturacion incluye sale_id y sunat_mensaje para enlace a venta y estado claro', function () {
    $user = User::factory()->create();
    $user->assignRole('Vendedor');
    $sede = Sede::factory()->almacen()->create();

    $sale = Sale::factory()->create([
        'vendedor_id' => $user->id,
        'sede_id' => $sede->id,
    ]);

    $document = ElectronicDocument::factory()->create([
        'sale_id' => $sale->id,
        'sunat_estado' => 'rechazado',
        'sunat_codigo_respuesta' => '2108',
        'sunat_mensaje' => 'El RUC del receptor no está activo',
    ]);

    $this->actingAs($user)
        ->get(route('vendedor.facturacion.index', ['current_team' => $user->currentTeam]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('vendedor/facturacion/index')
            ->has('documents.data', 1, fn (Assert $item) => $item
                ->where('id', $document->id)
                ->where('sale_id', $sale->id)
                ->where('sunat_mensaje', 'El RUC del receptor no está activo')
                ->etc()
            )
        );
});

test('el formulario de venta explica los bloqueos de emision y las unidades cotizadas faltantes', function () {
    $source = codigoDePantalla('js/pages/vendedor/ventas/nueva.tsx');

    expect($source)
        ->toContain('Define al menos una cuota antes de emitir.')
        ->toContain('Ajusta las cuotas: su suma debe coincidir con el total.')
        ->toContain('por escanear de ${unidadFaltante.nombre}.')
        ->toContain('disabled={form.processing}');
});

test('la cotizacion presenta la condicion de pago solo como opciones cerradas', function () {
    $source = codigoDePantalla('js/pages/vendedor/cotizaciones/nueva.tsx');

    expect($source)
        ->toContain("'Crédito 7 días'")
        ->toContain("'Crédito 15 días'")
        ->toContain("'Crédito 30 días'")
        ->not->toContain("form.setData( 'condicion_pago_propuesta', e.target.value");
});

test('los formularios operativos muestran una razon visible en lugar de bloquear acciones en silencio', function (string $archivo, string $mensaje) {
    $source = codigoDePantalla($archivo);

    expect($source)
        ->toContain('role="alert"')
        ->toContain($mensaje);
})->with([
    'entrega de tecnico campo' => [
        'js/pages/tecnico-campo/entregas/show.tsx',
        'Marca la conformidad del receptor antes de confirmar la entrega.',
    ],
    'instalacion de tecnico campo' => [
        'js/pages/tecnico-campo/instalaciones/show.tsx',
        'Marca la conformidad del cliente antes de finalizar la instalación.',
    ],
    'recojo de tecnico campo' => [
        'js/pages/tecnico-campo/recojos/show.tsx',
        'Marca la conformidad del cliente antes de registrar el recojo.',
    ],
]);

test('los formularios de almacen planta y gerencia muestran errores anidados o no ubicados', function (string $archivo) {
    $source = codigoDePantalla($archivo);

    expect($source)
        ->toContain('Object.values(')
        ->toContain('role="alert"');
})->with([
    'recepcion de almacen' => ['js/pages/almacen/recepciones/create.tsx'],
    'checklist de tecnico planta' => ['js/pages/tecnico-planta/checklist/create.tsx'],
    'productos de gerente' => ['js/pages/gerente/productos/index.tsx'],
]);
