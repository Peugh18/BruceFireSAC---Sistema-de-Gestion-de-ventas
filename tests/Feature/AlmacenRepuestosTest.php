<?php

use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\Reception;
use App\Models\Sede;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

if (! function_exists('almacenUserForRepuestosTest')) {
    function almacenUserForRepuestosTest(): User
    {
        $user = User::factory()->create();
        $user->assignRole('Almacen');

        return $user;
    }
}

test('flujo end-to-end de repuestos y componentes a granel (§84.11)', function () {
    $user = almacenUserForRepuestosTest();
    $sede = Sede::factory()->create(['tipo' => 'almacen', 'activo' => true]);

    // 1. Repuestos creados como Product con serializado = false
    $valvula = Product::factory()->create([
        'codigo' => 'VALV-12',
        'nombre' => 'Válvula de bronce 1/2 pulgada',
        'serializado' => false,
        'unidad_medida' => 'NIU',
        'precio_venta' => 25.00,
        'activo' => true,
    ]);

    $manometro = Product::factory()->create([
        'codigo' => 'MAN-150',
        'nombre' => 'Manómetro 150 PSI para extintor PQS',
        'serializado' => false,
        'unidad_medida' => 'NIU',
        'precio_venta' => 15.00,
        'activo' => true,
    ]);

    // 2. Recepción de proveedor con componentes a granel (Fase 4 rama no-serializada)
    $payloadRecepcion = [
        'proveedor' => 'Importadora de Válvulas y Manómetros SAC',
        'documento_referencia' => 'F005-001234',
        'fecha' => today()->toDateString(),
        'sede_almacen_id' => $sede->id,
        'observacion' => 'Lote de repuestos para taller de recargas',
        'items' => [
            [
                'product_id' => $valvula->id,
                'cantidad' => 50,
                'cantidad_conforme' => 50,
                'costo_unitario' => 12.00,
                'observacion_item' => null,
                'unidades' => [],
            ],
            [
                'product_id' => $manometro->id,
                'cantidad' => 100,
                'cantidad_conforme' => 95,
                'costo_unitario' => 7.50,
                'observacion_item' => '5 manómetros llegaron con esfera rota en caja húmeda',
                'unidades' => [],
            ],
        ],
    ];

    $this->actingAs($user)
        ->post(route('almacen.recepciones.store', ['current_team' => $user->currentTeam]), $payloadRecepcion)
        ->assertSessionHasNoErrors();

    $reception = Reception::first();
    expect($reception)->not->toBeNull()
        ->and($reception->total_items)->toBe(150)
        ->and($reception->total_conforme)->toBe(145)
        ->and($reception->total_no_conforme)->toBe(5);

    // Verificar Kardex: movimientos de ingreso a granel (sin unidades individuales)
    $movValvulas = InventoryMovement::where('product_id', $valvula->id)->first();
    expect($movValvulas)->not->toBeNull()
        ->and($movValvulas->cantidad)->toBe(50)
        ->and($movValvulas->tipo)->toBe('ingreso')
        ->and($movValvulas->inventory_unit_id)->toBeNull();

    $movManometros = InventoryMovement::where('product_id', $manometro->id)->first();
    expect($movManometros)->not->toBeNull()
        ->and($movManometros->cantidad)->toBe(95)
        ->and($movManometros->inventory_unit_id)->toBeNull();

    // 3. Consulta de Stock y Kardex (§84.7): los repuestos muestran su stock disponible
    $this->actingAs($user)
        ->get(route('almacen.stock.index', ['current_team' => $user->currentTeam]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('almacen/stock/index')
            ->where('items', function ($items) use ($valvula, $manometro, $sede) {
                $itemValvula = collect($items)->firstWhere('id', $valvula->id);
                $itemManometro = collect($items)->firstWhere('id', $manometro->id);

                return $itemValvula['stock_disponible_total'] === 50 &&
                       $itemValvula['stock_por_sede'][$sede->id] === 50 &&
                       $itemManometro['stock_disponible_total'] === 95 &&
                       $itemManometro['stock_por_sede'][$sede->id] === 95;
            })
        );

    // 4. Ajuste de stock directo sobre repuesto (§84.10): merma de 3 válvulas
    $payloadAjuste = [
        'product_id' => $valvula->id,
        'sede_id' => $sede->id,
        'tipo_ajuste' => 'decremento',
        'cantidad' => 3,
        'motivo' => 'Merma por falla de rosca en 3 válvulas descartadas en prueba',
    ];

    $this->actingAs($user)
        ->post(route('almacen.ajustes.store', ['current_team' => $user->currentTeam]), $payloadAjuste)
        ->assertSessionHasNoErrors();

    // Stock final de válvulas en Kardex: 50 - 3 = 47
    $stockFinalValvulas = InventoryMovement::where('product_id', $valvula->id)->sum('cantidad');
    expect($stockFinalValvulas)->toBe(47);
});
