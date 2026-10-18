<?php

use App\Enums\TeamRole;
use App\Models\Client;
use App\Models\DispatchGuide;
use App\Models\ElectronicDocument;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Sede;
use App\Models\Team;
use App\Models\User;
use App\Services\Billing\GuiaRemisionPrefill;
use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function vendedorGreContexto(string $rol = 'Vendedor'): array
{
    $sede = Sede::factory()->create(['ubigeo' => '130101', 'direccion' => 'Av. España 100']);
    $user = User::factory()->create(['sede_id' => $sede->id]);
    $user->assignRole($rol);
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Admin->value]);

    return [$user, $team, $sede];
}

function ventaGreCliente(User $user, Sede $sede): Sale
{
    $cliente = Client::factory()->create(['tipo_documento' => 'ruc', 'numero_documento' => '20123456786', 'razon_social' => 'Cliente real SAC', 'ubigeo' => '130101']);
    $sale = Sale::factory()->create(['estado' => 'confirmada', 'sede_id' => $sede->id, 'vendedor_id' => $user->id, 'client_id' => $cliente->id]);
    ElectronicDocument::factory()->create(['sale_id' => $sale->id, 'tipo' => 'factura', 'serie' => 'F001', 'correlativo' => 15, 'sunat_estado' => 'aceptado']);
    SaleItem::factory()->create(['sale_id' => $sale->id, 'product_id' => Product::factory()->create(['nombre' => 'Extintor PQS 6 kg'])->id, 'cantidad' => 2]);

    return $sale;
}

test('solo vendedor puede gestionar guías aunque otro rol reciba permisos directos', function (string $rol) {
    [$user, $team] = vendedorGreContexto($rol);
    $user->givePermissionTo(['guias_remision.view', 'guias_remision.create']);

    $this->actingAs($user)->get(route('guias.index', $team))->assertForbidden();
    $this->get(route('guias.create', $team))->assertForbidden();
    $this->post(route('guias.store', $team), [])->assertForbidden();
    $this->assertDatabaseCount('dispatch_guides', 0);
})->with(['Gerente', 'Almacen', 'TecnicoCampo', 'TecnicoPlanta']);

test('guía carga cliente comprobante y bienes de una venta confirmada', function () {
    [$user, $team, $sede] = vendedorGreContexto();
    $sale = ventaGreCliente($user, $sede);

    $this->actingAs($user)->get(route('guias.create', ['current_team' => $team, 'origen' => 'venta', 'id' => $sale->id]))
        ->assertInertia(fn (AssertableInertia $page) => $page->component('guias/form')
            ->where('datos.sale_id', $sale->id)->where('datos.doc_relacionado_numero', 'F001-15')
            ->where('datos.destinatario_nombre', 'Cliente real SAC')->where('datos.items.0.cantidad', 2));
});

test('guía rechaza venta sin comprobante y orígenes internos', function () {
    [$user, $team, $sede] = vendedorGreContexto();
    $sale = Sale::factory()->create(['sede_id' => $sede->id, 'vendedor_id' => $user->id, 'estado' => 'confirmada']);

    $this->actingAs($user)->get(route('guias.create', ['current_team' => $team, 'origen' => 'venta', 'id' => $sale->id]))->assertSessionHasErrors('sale_id');
    $this->get(route('guias.create', ['current_team' => $team, 'origen' => 'traslado', 'id' => 1]))->assertNotFound();
    $this->get(route('guias.create', ['current_team' => $team, 'origen' => 'recojo', 'id' => 1]))->assertNotFound();
    $this->assertDatabaseCount('dispatch_guides', 0);
});

test('vendedor agrega transporte en la guía y el servidor conserva los datos de la venta', function () {
    [$user, $team, $sede] = vendedorGreContexto();
    $sale = ventaGreCliente($user, $sede);
    $datos = app(GuiaRemisionPrefill::class)->desdeVentaParaCliente($sale);
    $datos = [...$datos, 'fecha_traslado' => today()->toDateString(), 'modalidad' => '02', 'peso_bruto' => 15,
        'partida_ubigeo' => '130101', 'llegada_ubigeo' => '130101', 'llegada_direccion' => 'Av. Cliente 200',
        'nuevo_vehiculo_placa' => 'ABC-123', 'nuevo_vehiculo_categoria' => 'N',
        'nuevo_conductor_dni' => '12345678', 'nuevo_conductor_nombres' => 'Juan', 'nuevo_conductor_apellidos' => 'Pérez', 'nuevo_conductor_licencia' => 'Q12345678',
        'destinatario_nombre' => 'Alterado', 'doc_relacionado_numero' => 'F999-999',
        'items' => [['descripcion' => 'Alterado', 'unidad' => 'NIU', 'cantidad' => 999]]];

    $this->actingAs($user)->post(route('guias.store', $team), $datos)->assertSessionHasNoErrors()->assertRedirect();

    $guide = DispatchGuide::sole();
    expect($guide->destinatario_nombre)->toBe('Cliente real SAC');
    expect($guide->doc_relacionado_numero)->toBe('F001-15');
    expect($guide->items->sole()->descripcion)->toBe('Extintor PQS 6 kg');
    expect((float) $guide->items->sole()->cantidad)->toBe(2.0);
    $this->assertDatabaseHas('transport_vehicles', ['id' => $guide->transport_vehicle_id, 'placa' => 'ABC123']);
    $this->assertDatabaseHas('drivers', ['id' => $guide->driver_id, 'dni' => '12345678', 'licencia' => 'Q12345678']);
});

test('guía rechaza ventas ajenas al vendedor antes de copiar sus datos', function () {
    [$user, $team, $sede] = vendedorGreContexto();
    $otro = User::factory()->create(['sede_id' => $sede->id]);
    $sale = ventaGreCliente($otro, $sede);

    $this->actingAs($user)->get(route('guias.create', ['current_team' => $team, 'origen' => 'venta', 'id' => $sale->id]))->assertNotFound();
    $this->assertDatabaseCount('dispatch_guides', 0);
});

test('guía no se crea si falta la venta aunque el navegador envíe un comprobante', function () {
    [$user, $team] = vendedorGreContexto();

    $this->actingAs($user)->post(route('guias.store', $team), ['doc_relacionado_tipo' => '01', 'doc_relacionado_numero' => 'F001-1'])->assertSessionHasErrors('sale_id');
    $this->assertDatabaseCount('dispatch_guides', 0);
});
