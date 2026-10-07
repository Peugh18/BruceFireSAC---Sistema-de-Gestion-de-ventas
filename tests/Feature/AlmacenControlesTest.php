<?php

use App\Actions\Almacen\CreateReception;
use App\Actions\Almacen\TransferInventory;
use App\Models\InventoryMovement;
use App\Models\InventoryTransfer;
use App\Models\InventoryUnit;
use App\Models\Product;
use App\Models\Sede;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->almacen = Sede::factory()->almacen()->create();
    $this->otroAlmacen = Sede::factory()->almacen()->create();
    $this->almacenero = User::factory()->create(['sede_id' => $this->almacen->id]);
    $this->almacenero->assignRole('Almacen');
    $this->team = ['current_team' => $this->almacenero->currentTeam];
});

function kardex(Product $product, Sede $sede, int $cantidad): void
{
    (new InventoryMovement)->forceFill(['product_id' => $product->id, 'sede_id' => $sede->id, 'tipo' => 'ingreso', 'cantidad' => $cantidad, 'observacion' => 'prueba'])->save();
}

function ajustar(array $datos)
{
    return test()->actingAs(test()->almacenero)->post(route('almacen.ajustes.store', test()->team), [
        'motivo' => 'Conteo físico del almacén de prueba',
        'cantidad' => 1,
        ...$datos,
    ]);
}

test('un ajuste no toca otro almacen, no deja el stock en negativo ni revive un extintor vendido', function () {
    $guantes = Product::factory()->create(['serializado' => false]);
    kardex($guantes, $this->almacen, 3);
    kardex($guantes, $this->otroAlmacen, 50);

    ajustar(['product_id' => $guantes->id, 'sede_id' => $this->otroAlmacen->id, 'tipo_ajuste' => 'decremento'])->assertSessionHasErrors('sede_id');
    ajustar(['product_id' => $guantes->id, 'sede_id' => $this->almacen->id, 'tipo_ajuste' => 'decremento', 'cantidad' => 4])->assertSessionHasErrors('cantidad');
    ajustar(['product_id' => $guantes->id, 'sede_id' => $this->almacen->id, 'tipo_ajuste' => 'decremento', 'cantidad' => 3])->assertSessionHasNoErrors();
    expect(InventoryMovement::saldo($guantes->id, $this->almacen->id))->toBe(0);

    $extintor = Product::factory()->create(['serializado' => true]);
    $vendido = InventoryUnit::factory()->create(['product_id' => $extintor->id, 'sede_almacen_id' => $this->almacen->id, 'estado' => 'vendido']);
    ajustar(['product_id' => $extintor->id, 'inventory_unit_id' => $vendido->id, 'sede_id' => $this->almacen->id, 'tipo_ajuste' => 'incremento'])->assertSessionHasErrors('inventory_unit_id');
    expect($vendido->fresh()->estado)->toBe('vendido');

    // Un extintor con serie entra por Recepciones, no sumando sin unidad.
    ajustar(['product_id' => $extintor->id, 'sede_id' => $this->almacen->id, 'tipo_ajuste' => 'incremento'])->assertSessionHasErrors('inventory_unit_id');
});

test('la recepcion se registra solo en el almacen propio y al corregirla no toca partidas ajenas ni deja negativo', function () {
    $tornillos = Product::factory()->create(['serializado' => false]);

    $this->actingAs($this->almacenero)->post(route('almacen.recepciones.store', $this->team), [
        'proveedor' => 'Proveedor SAC', 'fecha' => today()->toDateString(), 'sede_almacen_id' => $this->otroAlmacen->id,
        'items' => [['product_id' => $tornillos->id, 'cantidad' => 5, 'cantidad_conforme' => 5]],
    ])->assertSessionHasErrors('sede_almacen_id');

    $mia = app(CreateReception::class)->handle(['proveedor' => 'A', 'fecha' => today()->toDateString(), 'sede_almacen_id' => $this->almacen->id], [['product_id' => $tornillos->id, 'cantidad' => 10, 'cantidad_conforme' => 10]], $this->almacenero);
    $ajena = app(CreateReception::class)->handle(['proveedor' => 'B', 'fecha' => today()->toDateString(), 'sede_almacen_id' => $this->almacen->id], [['product_id' => $tornillos->id, 'cantidad' => 4, 'cantidad_conforme' => 4]], $this->almacenero);
    $partidaAjena = $ajena->items()->first();

    $this->actingAs($this->almacenero)->put(route('almacen.recepciones.update', [...$this->team, 'reception' => $mia]), [
        'proveedor' => 'A', 'fecha' => today()->toDateString(),
        'items' => [['id' => $partidaAjena->id, 'cantidad' => 4, 'cantidad_conforme' => 0, 'observacion_item' => 'Vino roto']],
    ])->assertNotFound();
    expect($partidaAjena->fresh()->cantidad_conforme)->toBe(4);

    // Se vendieron 12 de los 14: ya no se pueden retirar 10 por no conformes.
    (new InventoryMovement)->forceFill(['product_id' => $tornillos->id, 'sede_id' => $this->almacen->id, 'tipo' => 'salida_venta', 'cantidad' => -12, 'observacion' => 'venta'])->save();
    $this->actingAs($this->almacenero)->put(route('almacen.recepciones.update', [...$this->team, 'reception' => $mia]), [
        'proveedor' => 'A', 'fecha' => today()->toDateString(),
        'items' => [['id' => $mia->items()->first()->id, 'cantidad' => 10, 'cantidad_conforme' => 0, 'observacion_item' => 'Vino roto']],
    ])->assertSessionHasErrors('items');
    expect(InventoryMovement::saldo($tornillos->id, $this->almacen->id))->toBe(2);
});

test('el traslado no saca mas de lo que hay y el gerente elige el almacen de origen', function () {
    $cascos = Product::factory()->create(['serializado' => false]);
    kardex($cascos, $this->almacen, 5);

    $this->actingAs($this->almacenero)->post(route('almacen.traslados.store', $this->team), [
        'destination_sede_id' => $this->otroAlmacen->id, 'product_id' => $cascos->id, 'quantity' => 6,
    ])->assertSessionHasErrors('quantity');

    $gerente = User::factory()->create();
    $gerente->assignRole('Gerente');
    $this->actingAs($gerente)->get(route('almacen.traslados.index', ['current_team' => $gerente->currentTeam, 'origen_sede_id' => $this->almacen->id]))->assertOk();
    $this->actingAs($gerente)->post(route('almacen.traslados.store', ['current_team' => $gerente->currentTeam]), [
        'origen_sede_id' => $this->almacen->id, 'destination_sede_id' => $this->otroAlmacen->id, 'product_id' => $cascos->id, 'quantity' => 2,
    ])->assertSessionHasNoErrors();

    // En tránsito: ya salió del origen, todavía no entró al destino.
    expect(InventoryMovement::saldo($cascos->id, $this->otroAlmacen->id))->toBe(0);
    app(TransferInventory::class)->confirmar(InventoryTransfer::query()->sole(), $gerente);

    expect(InventoryMovement::saldo($cascos->id, $this->almacen->id))->toBe(3)
        ->and(InventoryMovement::saldo($cascos->id, $this->otroAlmacen->id))->toBe(2);
});

test('el inicio de almacen marca bajo minimo a los productos sin serie segun su kardex', function () {
    $bajo = Product::factory()->create(['nombre' => 'Lentes de seguridad', 'serializado' => false, 'stock_minimo' => 10, 'activo' => true]);
    $bien = Product::factory()->create(['nombre' => 'Botas talla 42', 'serializado' => false, 'stock_minimo' => 5, 'activo' => true]);
    kardex($bajo, $this->almacen, 4);
    kardex($bien, $this->almacen, 30);

    $props = $this->actingAs($this->almacenero)->get(route('almacen.dashboard', $this->team))->assertOk()->viewData('page')['props'];

    expect(collect($props['productos_bajo_minimo'])->pluck('nombre')->all())->toBe(['Lentes de seguridad'])
        ->and($props['productos_bajo_minimo'][0]['stock_disponible'])->toBe(4);
});
