<?php

use App\Actions\Almacen\CreateStockAdjustment;
use App\Actions\Almacen\TransferInventory;
use App\Actions\Sales\RevertSale;
use App\Models\CashRegister;
use App\Models\Client;
use App\Models\InventoryMovement;
use App\Models\InventoryTransfer;
use App\Models\Product;
use App\Models\ProductLot;
use App\Models\Sale;
use App\Models\Sede;
use App\Models\User;
use App\Services\Inventory\StockPorLote;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->almacen = Sede::factory()->mixta()->create();
    $this->guantes = Product::factory()->create([
        'nombre' => 'Guante de nitrilo',
        'serializado' => false,
        'controla_lote' => true,
        'unidad_medida' => 'PR',
        'unidad_compra' => 'CAJA',
        'factor_compra' => 50,
        'precio_venta' => 10,
    ]);
    $this->almacenero = User::factory()->create(['sede_id' => $this->almacen->id]);
    $this->almacenero->assignRole('Almacen');
});

/**
 * Ingresa un lote directo al Kardex.
 */
function loteConStock(string $lote, ?string $vence, int $cantidad, ?Sede $sede = null): ProductLot
{
    $movimiento = app(StockPorLote::class)->ingresar(test()->guantes, ($sede ?? test()->almacen)->id, $cantidad, ['tipo' => 'ingreso'], $lote, $vence);

    return ProductLot::findOrFail($movimiento->product_lot_id);
}

function saldoDelLote(ProductLot $lote): int
{
    return (int) InventoryMovement::where('product_lot_id', $lote->id)->sum('cantidad');
}

test('la recepcion de un EPP con lote pide lote y vencimiento y no recibe lo vencido', function () {
    $team = ['current_team' => $this->almacenero->currentTeam];
    $partida = ['product_id' => $this->guantes->id, 'cantidad' => 100, 'cantidad_conforme' => 100];
    $recepcion = ['proveedor' => 'Proveedor EPP', 'fecha' => today()->toDateString(), 'sede_almacen_id' => $this->almacen->id];

    $this->actingAs($this->almacenero)
        ->post(route('almacen.recepciones.store', $team), [...$recepcion, 'items' => [$partida]])
        ->assertSessionHasErrors(['items.0.lote', 'items.0.fecha_vencimiento']);

    $this->actingAs($this->almacenero)
        ->post(route('almacen.recepciones.store', $team), [...$recepcion, 'items' => [[...$partida, 'lote' => 'L-1', 'fecha_vencimiento' => today()->subDay()->toDateString()]]])
        ->assertSessionHasErrors('items.0.fecha_vencimiento');

    $this->actingAs($this->almacenero)
        ->post(route('almacen.recepciones.store', $team), [...$recepcion, 'items' => [[...$partida, 'lote' => 'l-24a', 'fecha_vencimiento' => today()->addYear()->toDateString()]]])
        ->assertSessionHasNoErrors();

    $lote = ProductLot::sole();

    expect($lote->lote)->toBe('L-24A')
        ->and($lote->fecha_vencimiento->toDateString())->toBe(today()->addYear()->toDateString())
        ->and(saldoDelLote($lote))->toBe(100);
});

test('la venta saca primero lo que vence primero y la anulacion lo devuelve a su lote', function () {
    $tarde = loteConStock('TARDE', today()->addDays(200)->toDateString(), 5);
    $pronto = loteConStock('PRONTO', today()->addDays(20)->toDateString(), 5);
    $vendedor = User::factory()->create(['sede_id' => $this->almacen->id]);
    $vendedor->assignRole('Vendedor');
    CashRegister::factory()->create(['vendedor_id' => $vendedor->id]);

    $this->actingAs($vendedor)
        ->post(route('vendedor.ventas.store', ['current_team' => $vendedor->currentTeam]), [
            'client_id' => Client::factory()->create()->id,
            'fecha' => today()->toDateString(),
            'destino' => 'local_cliente',
            'condicion_pago' => 'contado',
            'medio_pago' => 'yape',
            'comprobante_tipo' => 'nota_venta',
            'items' => [['tipo_linea' => 'producto', 'product_id' => $this->guantes->id, 'cantidad' => 7, 'precio_unitario' => 10]],
        ])
        ->assertSessionHasNoErrors();

    expect(saldoDelLote($pronto))->toBe(0)
        ->and(saldoDelLote($tarde))->toBe(3);

    app(RevertSale::class)->handle(Sale::sole(), 'por prueba');

    expect(saldoDelLote($pronto))->toBe(5)
        ->and(saldoDelLote($tarde))->toBe(5);
});

test('un lote vencido no se vende y el mensaje lo dice', function () {
    loteConStock('VIEJO', today()->subDay()->toDateString(), 10);
    loteConStock('NUEVO', today()->addMonths(6)->toDateString(), 2);

    expect(fn () => app(StockPorLote::class)->sacar($this->guantes, $this->almacen->id, 3, ['tipo' => 'salida_venta'], 'items', verbo: 'vender'))
        ->toThrow(ValidationException::class, 'hay 2 vigentes (10 vencidos no se venden)');
});

test('el stock de antes de activar los lotes sale primero', function () {
    InventoryMovement::create(['product_id' => $this->guantes->id, 'sede_id' => $this->almacen->id, 'tipo' => 'ingreso', 'cantidad' => 4]);
    $lote = loteConStock('A1', today()->addMonth()->toDateString(), 10);

    app(StockPorLote::class)->sacar($this->guantes, $this->almacen->id, 6, ['tipo' => 'salida_venta']);

    expect(saldoDelLote($lote))->toBe(8)
        ->and((int) InventoryMovement::whereNull('product_lot_id')->where('product_id', $this->guantes->id)->sum('cantidad'))->toBe(0);
});

test('el traslado lleva el lote con su vencimiento al otro almacen', function () {
    $destino = Sede::factory()->almacen()->create();
    $vence = today()->addMonths(3)->toDateString();
    $origen = loteConStock('T-9', $vence, 10);
    $gerente = User::factory()->create();
    $gerente->assignRole('Gerente');

    app(TransferInventory::class)->handle($this->almacen->id, ['destination_sede_id' => $destino->id, 'product_id' => $this->guantes->id, 'quantity' => 4], $gerente);
    app(TransferInventory::class)->confirmar(InventoryTransfer::query()->sole(), $gerente);

    $llegado = ProductLot::where('sede_id', $destino->id)->sole();

    expect($llegado->lote)->toBe('T-9')
        ->and($llegado->fecha_vencimiento->toDateString())->toBe($vence)
        ->and(saldoDelLote($llegado))->toBe(4)
        ->and(saldoDelLote($origen))->toBe(6);
});

test('se da de baja un lote vencido puntual y un ingreso por ajuste pide lote', function () {
    $vencido = loteConStock('VENC', today()->subWeek()->toDateString(), 6);
    $vigente = loteConStock('OK', today()->addYear()->toDateString(), 6);

    app(CreateStockAdjustment::class)->handle([
        'product_id' => $this->guantes->id,
        'sede_id' => $this->almacen->id,
        'tipo_ajuste' => 'decremento',
        'cantidad' => 6,
        'product_lot_id' => $vencido->id,
        'motivo' => 'Baja de lote vencido',
    ], $this->almacenero);

    expect(saldoDelLote($vencido))->toBe(0)
        ->and(saldoDelLote($vigente))->toBe(6);

    $this->actingAs($this->almacenero)
        ->post(route('almacen.ajustes.store', ['current_team' => $this->almacenero->currentTeam]), [
            'product_id' => $this->guantes->id,
            'sede_id' => $this->almacen->id,
            'tipo_ajuste' => 'incremento',
            'cantidad' => 2,
            'motivo' => 'Conteo físico del mes',
        ])
        ->assertSessionHasErrors('lote');
});

test('un mismo lote no puede tener dos vencimientos', function () {
    loteConStock('DUP', today()->addMonth()->toDateString(), 1);

    expect(fn () => loteConStock('DUP', today()->addMonths(2)->toDateString(), 1))
        ->toThrow(ValidationException::class, 'ya está registrado con vencimiento');
});

test('un producto con serie no se controla por lote y la caja guarda su equivalencia', function () {
    $gerente = User::factory()->create();
    $gerente->assignRole('Gerente');
    $team = ['current_team' => $gerente->currentTeam];
    $base = ['codigo' => 'EPP-01', 'nombre' => 'Mascarilla N95', 'unidad_medida' => 'NIU', 'precio_venta' => 5];

    $this->actingAs($gerente)
        ->post(route('gerente.productos.store', $team), [...$base, 'serializado' => true, 'controla_lote' => true])
        ->assertSessionHasErrors('controla_lote');

    $this->actingAs($gerente)
        ->post(route('gerente.productos.store', $team), [...$base, 'controla_lote' => true, 'unidad_compra' => 'caja', 'factor_compra' => 20])
        ->assertSessionHasNoErrors();

    expect(Product::where('codigo', 'EPP-01')->sole())
        ->controla_lote->toBeTrue()
        ->unidad_compra->toBe('CAJA')
        ->factor_compra->toBe(20);
});
