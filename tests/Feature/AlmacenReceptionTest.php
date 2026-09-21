<?php

use App\Models\InventoryMovement;
use App\Models\InventorySequence;
use App\Models\InventoryUnit;
use App\Models\Product;
use App\Models\Reception;
use App\Models\Sede;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

if (! function_exists('almacenUserForReceptionTest')) {
    function almacenUserForReceptionTest(): User
    {
        $user = User::factory()->create();
        $user->assignRole('Almacen');

        return $user;
    }
}

test('almacen user can view recepciones index and create page', function () {
    $user = almacenUserForReceptionTest();
    $sede = Sede::factory()->create(['tipo' => 'almacen', 'activo' => true]);

    $this->actingAs($user)
        ->get(route('almacen.recepciones.index', ['current_team' => $user->currentTeam]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('almacen/recepciones/index')
            ->has('receptions')
            ->has('sedes')
            ->has('kpis')
        );

    $this->actingAs($user)
        ->get(route('almacen.recepciones.create', ['current_team' => $user->currentTeam]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('almacen/recepciones/create')
            ->has('sedes')
            ->has('products')
        );
});

test('recepcion creates inventory units and movements for conforming items with BF-EQ correlative', function () {
    $user = almacenUserForReceptionTest();
    $sede = Sede::factory()->create(['tipo' => 'almacen', 'activo' => true]);

    $prodSerial = Product::factory()->create([
        'codigo' => 'EXT-PQS-6KG',
        'nombre' => 'Extintor PQS 6kg ABC',
        'serializado' => true,
        'activo' => true,
    ]);

    $prodNoSerial = Product::factory()->create([
        'codigo' => 'MANG-15M',
        'nombre' => 'Manguera contra incendio 1.5 pulg',
        'serializado' => false,
        'activo' => true,
    ]);

    $payload = [
        'proveedor' => 'Distribuidora Fuego SAC',
        'documento_referencia' => 'F001-00045678',
        'fecha' => now()->toDateString(),
        'sede_almacen_id' => $sede->id,
        'observacion' => 'Llegó en camión placa ABC-123',
        'items' => [
            [
                'product_id' => $prodSerial->id,
                'cantidad' => 2,
                'cantidad_conforme' => 2,
                'costo_unitario' => 65.50,
                'observacion_item' => null,
                'unidades' => [
                    [
                        'numero_serie' => 'SN-EXT-001',
                        'marca' => 'Buckeye',
                        'anio_fabricacion' => 2026,
                    ],
                    [
                        'numero_serie' => 'SN-EXT-002',
                        'marca' => 'Buckeye',
                        'anio_fabricacion' => 2026,
                    ],
                ],
            ],
            [
                'product_id' => $prodNoSerial->id,
                'cantidad' => 5,
                'cantidad_conforme' => 5,
                'costo_unitario' => 120.00,
                'observacion_item' => null,
                'unidades' => [],
            ],
        ],
    ];

    $response = $this->actingAs($user)
        ->post(route('almacen.recepciones.store', ['current_team' => $user->currentTeam]), $payload);

    $reception = Reception::first();
    expect($reception)->not->toBeNull()
        ->and($reception->proveedor)->toBe('Distribuidora Fuego SAC')
        ->and($reception->documento_referencia)->toBe('F001-00045678')
        ->and($reception->total_items)->toBe(7)
        ->and($reception->total_conforme)->toBe(7)
        ->and($reception->total_no_conforme)->toBe(0);

    $response->assertRedirect(route('almacen.recepciones.show', [
        'current_team' => $user->currentTeam,
        'reception' => $reception->id,
    ]));

    // Correlative check: InventorySequence was initialized and incremented
    $sequence = InventorySequence::where('nombre', 'equipment_serial')->first();
    expect($sequence)->not->toBeNull()
        ->and($sequence->correlativo_actual)->toBe(2);

    // Units created for serial product have BF-EQ-000001 and BF-EQ-000002
    $units = InventoryUnit::where('product_id', $prodSerial->id)->orderBy('id')->get();
    expect($units)->toHaveCount(2)
        ->and($units[0]->codigo_interno)->toBe('BF-EQ-000001')
        ->and($units[0]->numero_serie)->toBe('BF-EQ-000001')
        ->and($units[0]->marca)->toBe('Buckeye')
        ->and($units[0]->anio_fabricacion)->toBe(2026)
        ->and($units[0]->estado)->toBe('disponible')
        ->and($units[1]->codigo_interno)->toBe('BF-EQ-000002')
        ->and($units[1]->numero_serie)->toBe('BF-EQ-000002');

    // Kardex movements created
    $movements = InventoryMovement::where('referencia_type', Reception::class)
        ->where('referencia_id', $reception->id)
        ->get();

    // 2 movements for serial units + 1 movement for bulk product (cantidad = 5)
    expect($movements)->toHaveCount(3);

    $bulkMovement = $movements->where('product_id', $prodNoSerial->id)->first();
    expect($bulkMovement)->not->toBeNull()
        ->and($bulkMovement->cantidad)->toBe(5)
        ->and($bulkMovement->tipo)->toBe('ingreso');
});

test('rule 2: mercaderia no conforme never creates InventoryUnit or InventoryMovement and requires observacion', function () {
    $user = almacenUserForReceptionTest();
    $sede = Sede::factory()->create(['tipo' => 'almacen', 'activo' => true]);

    $prod = Product::factory()->create([
        'codigo' => 'EXT-10KG',
        'nombre' => 'Extintor 10kg',
        'serializado' => true,
        'activo' => true,
    ]);

    // Validation fails if observacion_item is missing when cantidad_conforme < cantidad
    $invalidPayload = [
        'proveedor' => 'Proveedor Fallas SAC',
        'documento_referencia' => 'F001-000999',
        'fecha' => now()->toDateString(),
        'sede_almacen_id' => $sede->id,
        'items' => [
            [
                'product_id' => $prod->id,
                'cantidad' => 3,
                'cantidad_conforme' => 2, // 1 non-conforming, but no observation
                'costo_unitario' => 80.00,
                'observacion_item' => '',
                'unidades' => [
                    ['numero_serie' => 'SER-1', 'marca' => 'ABC', 'anio_fabricacion' => 2026],
                    ['numero_serie' => 'SER-2', 'marca' => 'ABC', 'anio_fabricacion' => 2026],
                ],
            ],
        ],
    ];

    $this->actingAs($user)
        ->post(route('almacen.recepciones.store', ['current_team' => $user->currentTeam]), $invalidPayload)
        ->assertSessionHasErrors(['items.0.observacion_item']);

    // Now valid with observation: 3 arrived, only 2 conforming, 1 damaged
    $validPayload = $invalidPayload;
    $validPayload['items'][0]['observacion_item'] = '1 extintor llegó con manómetro roto y golpe en el cuerpo';

    $this->actingAs($user)
        ->post(route('almacen.recepciones.store', ['current_team' => $user->currentTeam]), $validPayload)
        ->assertSessionHasNoErrors();

    $reception = Reception::first();
    expect($reception->total_items)->toBe(3)
        ->and($reception->total_conforme)->toBe(2)
        ->and($reception->total_no_conforme)->toBe(1);

    // EXACTLY 2 inventory units created (NEVER 3)
    $units = InventoryUnit::where('product_id', $prod->id)->get();
    expect($units)->toHaveCount(2);

    // EXACTLY 2 movements created (NEVER for non-conforming)
    $movements = InventoryMovement::where('product_id', $prod->id)->get();
    expect($movements)->toHaveCount(2);
    expect($movements->sum('cantidad'))->toBe(2);
});

test('rule 3: confirmed reception is editable and adjusts stock with new compensatory movement without modifying historical ones', function () {
    $user = almacenUserForReceptionTest();
    $sede = Sede::factory()->create(['tipo' => 'almacen', 'activo' => true]);

    $prodNoSerial = Product::factory()->create([
        'codigo' => 'GAB-01',
        'nombre' => 'Gabinete contra incendio metálico',
        'serializado' => false,
        'activo' => true,
    ]);

    // Initial reception: 10 items conforming
    $payload = [
        'proveedor' => 'Metalúrgica del Fuego SAC',
        'documento_referencia' => 'F002-1234',
        'fecha' => now()->toDateString(),
        'sede_almacen_id' => $sede->id,
        'items' => [
            [
                'product_id' => $prodNoSerial->id,
                'cantidad' => 10,
                'cantidad_conforme' => 10,
                'costo_unitario' => 150.00,
                'unidades' => [],
            ],
        ],
    ];

    $this->actingAs($user)
        ->post(route('almacen.recepciones.store', ['current_team' => $user->currentTeam]), $payload);

    $reception = Reception::first();
    $item = $reception->items->first();

    $initialMovements = InventoryMovement::where('referencia_type', Reception::class)
        ->where('referencia_id', $reception->id)
        ->get();
    expect($initialMovements)->toHaveCount(1);
    $initialMovementId = $initialMovements->first()->id;

    // Now edit: user notices 2 cabinets were actually dented/non-conforming, so conforming is corrected to 8 (-2)
    $updatePayload = [
        'proveedor' => 'Metalúrgica del Fuego SAC',
        'documento_referencia' => 'F002-1234-REV',
        'fecha' => now()->toDateString(),
        'observacion' => 'Revisión posterior de calidad',
        'items' => [
            [
                'id' => $item->id,
                'cantidad' => 10,
                'cantidad_conforme' => 8,
                'costo_unitario' => 150.00,
                'observacion_item' => '2 gabinetes golpeados en la base detectados en desempaque',
                'unidades_nuevas' => [],
            ],
        ],
    ];

    $this->actingAs($user)
        ->put(route('almacen.recepciones.update', [
            'current_team' => $user->currentTeam,
            'reception' => $reception->id,
        ]), $updatePayload)
        ->assertSessionHasNoErrors();

    // Verify reception header updated
    $reception->refresh();
    expect($reception->documento_referencia)->toBe('F002-1234-REV')
        ->and($reception->total_items)->toBe(10)
        ->and($reception->total_conforme)->toBe(8)
        ->and($reception->total_no_conforme)->toBe(2);

    // Verify historical movement was NOT touched
    $originalMovement = InventoryMovement::find($initialMovementId);
    expect($originalMovement->cantidad)->toBe(10)
        ->and($originalMovement->tipo)->toBe('ingreso');

    // Verify a NEW compensatory 'ajuste' movement was created for -2
    $compensatoryMovements = InventoryMovement::where('referencia_type', Reception::class)
        ->where('referencia_id', $reception->id)
        ->where('tipo', 'ajuste')
        ->get();

    expect($compensatoryMovements)->toHaveCount(1);
    expect($compensatoryMovements->first()->cantidad)->toBe(-2);
    expect($compensatoryMovements->first()->observacion)->toContain('Corrección Recepción');

    // Net stock for this product in Kardex is now 10 - 2 = 8
    $totalStock = InventoryMovement::where('product_id', $prodNoSerial->id)->sum('cantidad');
    expect($totalStock)->toBe(8);
});

test('recepcion editing can add conforming quantity and create extra units and positive compensatory movement', function () {
    $user = almacenUserForReceptionTest();
    $sede = Sede::factory()->create(['tipo' => 'almacen', 'activo' => true]);

    $prodSerial = Product::factory()->create([
        'codigo' => 'EXT-CO2-5LB',
        'nombre' => 'Extintor CO2 5lb',
        'serializado' => true,
        'activo' => true,
    ]);

    // Arrived 3, initially marked 2 conforming and 1 pending/non-conforming
    $payload = [
        'proveedor' => 'Extintores del Sur',
        'documento_referencia' => 'F003-999',
        'fecha' => now()->toDateString(),
        'sede_almacen_id' => $sede->id,
        'items' => [
            [
                'product_id' => $prodSerial->id,
                'cantidad' => 3,
                'cantidad_conforme' => 2,
                'costo_unitario' => 200.00,
                'observacion_item' => '1 pendiente de prueba hidrostatica previa',
                'unidades' => [
                    ['numero_serie' => 'CO2-01', 'marca' => 'Kidde', 'anio_fabricacion' => 2026],
                    ['numero_serie' => 'CO2-02', 'marca' => 'Kidde', 'anio_fabricacion' => 2026],
                ],
            ],
        ],
    ];

    $this->actingAs($user)
        ->post(route('almacen.recepciones.store', ['current_team' => $user->currentTeam]), $payload);

    $reception = Reception::first();
    expect($reception)->not->toBeNull();
    $item = $reception->items->first();

    // Now update: the 3rd unit was inspected and approved, conforming becomes 3 (+1)
    $updatePayload = [
        'proveedor' => 'Extintores del Sur',
        'documento_referencia' => 'F003-999',
        'fecha' => now()->toDateString(),
        'items' => [
            [
                'id' => $item->id,
                'cantidad' => 3,
                'cantidad_conforme' => 3,
                'costo_unitario' => 200.00,
                'observacion_item' => null,
                'unidades_nuevas' => [
                    ['numero_serie' => 'CO2-03', 'marca' => 'Kidde', 'anio_fabricacion' => 2026],
                ],
            ],
        ],
    ];

    $this->actingAs($user)
        ->put(route('almacen.recepciones.update', [
            'current_team' => $user->currentTeam,
            'reception' => $reception->id,
        ]), $updatePayload)
        ->assertSessionHasNoErrors();

    // Now 3 units exist
    $units = InventoryUnit::where('product_id', $prodSerial->id)->get();
    expect($units)->toHaveCount(3);
    expect($units->pluck('codigo_interno')->toArray())->toEqual([
        'BF-EQ-000001',
        'BF-EQ-000002',
        'BF-EQ-000003',
    ]);

    // Positive compensatory movement (+1) was created
    $ajuste = InventoryMovement::where('tipo', 'ajuste')
        ->where('referencia_id', $reception->id)
        ->first();
    expect($ajuste)->not->toBeNull()
        ->and($ajuste->cantidad)->toBe(1);
});

test('user without Almacen role cannot access recepciones', function () {
    $user = User::factory()->create();
    $user->assignRole('Vendedor');

    $this->actingAs($user)
        ->get(route('almacen.recepciones.index', ['current_team' => $user->currentTeam]))
        ->assertForbidden();
});
