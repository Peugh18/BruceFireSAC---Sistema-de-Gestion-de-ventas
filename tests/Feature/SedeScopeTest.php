<?php

use App\Actions\Sales\CreateSale;
use App\Models\CashRegister;
use App\Models\Client;
use App\Models\Installment;
use App\Models\InventoryUnit;
use App\Models\Product;
use App\Models\Quote;
use App\Models\Sale;
use App\Models\Sede;
use App\Models\ServiceOrder;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->sedeA = Sede::factory()->mixta()->create(['nombre' => 'Sede A']);
    $this->sedeB = Sede::factory()->mixta()->create(['nombre' => 'Sede B']);
});

function vendedorEnSede(Sede $sede): User
{
    $user = vendedorUser();
    $user->update(['sede_id' => $sede->id]);

    return $user->refresh();
}

function teamOf(User $user): array
{
    return ['current_team' => $user->currentTeam];
}

test('un trabajador con sede solo ve las ventas de su sede', function () {
    $vendedor = vendedorEnSede($this->sedeA);
    $propia = Sale::factory()->create(['sede_id' => $this->sedeA->id, 'vendedor_id' => $vendedor->id, 'fecha' => today()]);
    $ajena = Sale::factory()->create(['sede_id' => $this->sedeB->id, 'vendedor_id' => $vendedor->id, 'fecha' => today()]);

    $this->actingAs($vendedor)
        ->get(route('vendedor.ventas.index', teamOf($vendedor)))
        ->assertInertia(fn (Assert $page) => $page
            ->has('sales.data', 1)
            ->where('sales.data.0.id', $propia->id)
        );

    $this->actingAs($vendedor)->get(route('vendedor.ventas.show', [...teamOf($vendedor), 'sale' => $ajena]))->assertNotFound();
    $this->actingAs($vendedor)->get(route('vendedor.ventas.show', [...teamOf($vendedor), 'sale' => $propia]))->assertOk();
    $this->actingAs($vendedor)->post(route('vendedor.ventas.confirmar', [...teamOf($vendedor), 'sale' => $ajena]))->assertNotFound();
});

test('el gerente ve las ventas de todas las sedes y de todos los vendedores', function () {
    Sale::factory()->create(['sede_id' => $this->sedeA->id, 'fecha' => today()]);
    Sale::factory()->create(['sede_id' => $this->sedeB->id, 'fecha' => today()]);
    $gerente = User::factory()->create();
    // Un Gerente que también vende entra a la pantalla de ventas.
    $gerente->assignRole(['Gerente', 'Vendedor']);
    $gerente->update(['sede_id' => $this->sedeA->id]);

    $this->actingAs($gerente)->get(route('vendedor.ventas.index', teamOf($gerente)))
        ->assertInertia(fn (Assert $page) => $page->has('sales.data', 2));

    expect($gerente->refresh()->sedeRestringidaId())->toBeNull()
        ->and($gerente->vendedorRestringidoId())->toBeNull();
});

test('cada vendedor ve y corrige solo sus ventas, aunque el otro sea de su misma sede', function () {
    $yo = vendedorEnSede($this->sedeA);
    $companero = vendedorEnSede($this->sedeA);
    $mia = Sale::factory()->create(['sede_id' => $this->sedeA->id, 'vendedor_id' => $yo->id, 'fecha' => today()]);
    $suya = Sale::factory()->create(['sede_id' => $this->sedeA->id, 'vendedor_id' => $companero->id, 'fecha' => today()]);

    $this->actingAs($yo)
        ->get(route('vendedor.ventas.index', teamOf($yo)))
        ->assertInertia(fn (Assert $page) => $page
            ->has('sales.data', 1)
            ->where('sales.data.0.id', $mia->id)
        );

    $this->actingAs($yo)->get(route('vendedor.ventas.show', [...teamOf($yo), 'sale' => $suya]))->assertNotFound();
    $this->actingAs($yo)->get(route('vendedor.ventas.edit', [...teamOf($yo), 'sale' => $suya]))->assertNotFound();
    $this->actingAs($yo)->post(route('vendedor.ventas.anular', [...teamOf($yo), 'sale' => $suya]), ['motivo' => 'Prueba de permisos'])->assertNotFound();
    $this->actingAs($yo)->get(route('vendedor.ventas.show', [...teamOf($yo), 'sale' => $mia]))->assertOk();
});

test('la nueva venta de un trabajador con sede queda registrada en su sede aunque envíe otra', function () {
    $vendedor = vendedorEnSede($this->sedeA);
    $product = Product::factory()->create();
    $unit = InventoryUnit::factory()->create([
        'product_id' => $product->id,
        'sede_almacen_id' => $this->sedeA->id,
        'estado' => 'disponible',
    ]);

    $this->actingAs($vendedor)
        ->post(route('vendedor.ventas.store', teamOf($vendedor)), [
            'client_id' => Client::factory()->create()->id,
            'sede_id' => $this->sedeB->id,
            'fecha' => now()->toDateString(),
            'destino' => 'local_cliente',
            'condicion_pago' => 'contado',
            'comprobante_tipo' => 'nota_venta',
            'items' => [[
                'tipo_linea' => 'unidad_nueva',
                'numero_serie' => $unit->numero_serie,
                'product_id' => $product->id,
                'cantidad' => 1,
                'precio_unitario' => 50,
            ]],
        ])
        ->assertRedirect();

    expect(Sale::firstOrFail()->sede_id)->toBe($this->sedeA->id);

    $this->actingAs($vendedor)->get(route('vendedor.ventas.create', teamOf($vendedor)))
        ->assertInertia(fn (Assert $page) => $page->has('sedes', 1)->where('sedes.0.id', $this->sedeA->id));
});

test('una tienda vende con el stock del almacén al que está asignada', function () {
    $almacen = Sede::factory()->almacen()->create();
    $tienda = Sede::factory()->tienda()->create(['almacen_id' => $almacen->id]);
    $product = Product::factory()->create();
    $unit = InventoryUnit::factory()->create([
        'product_id' => $product->id,
        'sede_almacen_id' => $almacen->id,
        'estado' => 'disponible',
    ]);
    $vendedor = vendedorEnSede($tienda);

    $sale = app(CreateSale::class)->handle([
        'client_id' => Client::factory()->create()->id,
        'sede_id' => $tienda->id,
        'fecha' => now()->toDateString(),
        'destino' => 'local_cliente',
        'condicion_pago' => 'contado',
        'comprobante_tipo' => 'nota_venta',
    ], [[
        'tipo_linea' => 'unidad_nueva',
        'numero_serie' => $unit->numero_serie,
        'product_id' => $product->id,
        'cantidad' => 1,
        'precio_unitario' => 50,
    ]], $vendedor->id);

    expect($unit->refresh()->estado)->toBe('vendido')
        ->and($sale->sede_id)->toBe($tienda->id);

    $otraUnidad = InventoryUnit::factory()->create([
        'product_id' => $product->id,
        'sede_almacen_id' => $this->sedeB->id,
        'estado' => 'disponible',
    ]);

    $disponible = InventoryUnit::factory()->create([
        'product_id' => $product->id,
        'sede_almacen_id' => $almacen->id,
        'estado' => 'disponible',
    ]);

    $this->actingAs($vendedor)
        ->getJson(route('vendedor.ventas.escanear-serie', [...teamOf($vendedor), 'numero_serie' => $disponible->numero_serie, 'sede_almacen_id' => $tienda->id]))
        ->assertOk();

    $this->actingAs($vendedor)
        ->getJson(route('vendedor.ventas.escanear-serie', [...teamOf($vendedor), 'numero_serie' => $otraUnidad->numero_serie, 'sede_almacen_id' => $tienda->id]))
        ->assertStatus(422);
});

test('las cotizaciones y las órdenes de servicio se acotan a la sede del trabajador', function () {
    $vendedor = vendedorEnSede($this->sedeA);
    Quote::factory()->create(['sede_id' => $this->sedeA->id]);
    Quote::factory()->create(['sede_id' => $this->sedeB->id]);
    $ordenPropia = ServiceOrder::factory()->create(['sede_id' => $this->sedeA->id]);
    $ordenAjena = ServiceOrder::factory()->create(['sede_id' => $this->sedeB->id]);

    $this->actingAs($vendedor)->get(route('vendedor.cotizaciones.index', teamOf($vendedor)))
        ->assertInertia(fn (Assert $page) => $page->has('quotes.data', 1));

    $this->actingAs($vendedor)->get(route('vendedor.ordenes-servicio.index', teamOf($vendedor)))
        ->assertInertia(fn (Assert $page) => $page->has('orders.data', 1)->where('orders.data.0.id', $ordenPropia->id));

    $this->actingAs($vendedor)
        ->get(route('vendedor.ordenes-servicio.show', [...teamOf($vendedor), 'service_order' => $ordenAjena]))
        ->assertNotFound();
});

test('las cobranzas del vendedor solo incluyen cuotas de ventas de su sede', function () {
    $vendedor = vendedorEnSede($this->sedeA);
    $propia = Sale::factory()->create(['estado' => 'confirmada', 'sede_id' => $this->sedeA->id, 'vendedor_id' => $vendedor->id]);
    $ajena = Sale::factory()->create(['estado' => 'confirmada', 'sede_id' => $this->sedeB->id, 'vendedor_id' => $vendedor->id]);
    Installment::factory()->create(['sale_id' => $propia->id, 'estado' => 'pendiente']);
    Installment::factory()->create(['sale_id' => $ajena->id, 'estado' => 'pendiente']);

    $this->actingAs($vendedor)->get(route('vendedor.cobranzas.index', teamOf($vendedor)))
        ->assertInertia(fn (Assert $page) => $page->has('installments.data', 1));
});

test('la caja se abre siempre en la sede del trabajador', function () {
    $vendedor = vendedorEnSede($this->sedeA);

    $this->actingAs($vendedor)
        ->post(route('vendedor.caja.abrir', teamOf($vendedor)), [
            'sede_id' => $this->sedeB->id,
            'monto_apertura' => 100,
        ])
        ->assertRedirect();

    expect(CashRegister::where('vendedor_id', $vendedor->id)->value('sede_id'))->toBe($this->sedeA->id);
});

test('el almacén solo ve el stock y las recepciones de su almacén', function () {
    $almacenero = User::factory()->create(['sede_id' => $this->sedeA->id]);
    $almacenero->assignRole('Almacen');

    $this->actingAs($almacenero)
        ->get(route('almacen.stock.index', teamOf($almacenero)))
        ->assertInertia(fn (Assert $page) => $page->has('sedes', 1)->where('sedes.0.id', $this->sedeA->id));

    $this->actingAs($almacenero)
        ->get(route('almacen.recepciones.index', teamOf($almacenero)))
        ->assertInertia(fn (Assert $page) => $page->has('sedes', 1));

    $this->actingAs($almacenero)
        ->get(route('almacen.recepciones.create', teamOf($almacenero)))
        ->assertInertia(fn (Assert $page) => $page->has('sedes', 1));
});
