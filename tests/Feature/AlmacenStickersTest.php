<?php

use App\Models\InventoryMovement;
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

if (! function_exists('almacenUserForStickersTest')) {
    function almacenUserForStickersTest(): User
    {
        $user = User::factory()->create();
        $user->assignRole('Almacen');

        return $user;
    }
}

test('almacen user can generate and view inline pdf stickers for reception with serialized units', function () {
    $user = almacenUserForStickersTest();
    $sede = Sede::factory()->create(['tipo' => 'almacen', 'activo' => true]);

    $prod = Product::factory()->create([
        'codigo' => 'EXT-PQS-6',
        'nombre' => 'Extintor PQS 6kg',
        'serializado' => true,
    ]);

    $reception = Reception::create([
        'proveedor' => 'Proveedor Extintores SAC',
        'documento_referencia' => 'F001-12345',
        'fecha' => today(),
        'sede_almacen_id' => $sede->id,
        'user_id' => $user->id,
    ]);

    // Create 3 inventory units with movements linked to the reception
    for ($i = 1; $i <= 3; $i++) {
        $unit = InventoryUnit::create([
            'product_id' => $prod->id,
            'sede_almacen_id' => $sede->id,
            'numero_serie' => sprintf('BF-EQ-%06d', $i),
            'marca' => 'Buckeye',
            'capacidad' => '6',
            'agente' => 'pqs',
            'anio_fabricacion' => 2026,
            'estado' => 'disponible',
            'fecha_ingreso' => today(),
        ]);

        $movement = new InventoryMovement([
            'inventory_unit_id' => $unit->id,
            'product_id' => $prod->id,
            'sede_id' => $sede->id,
            'tipo' => 'ingreso',
            'cantidad' => 1,
            'user_id' => $user->id,
            'observacion' => "Recepción {$reception->id}",
        ]);
        $movement->referencia_type = Reception::class;
        $movement->referencia_id = $reception->id;
        $movement->save();
    }

    $response = $this->actingAs($user)
        ->get(route('almacen.recepciones.stickers', [
            'current_team' => $user->currentTeam,
            'reception' => $reception->id,
        ]));

    $response->assertOk();
    $response->assertHeader('Content-Type', 'application/pdf');
    expect($response->headers->get('Content-Disposition'))->toContain("stickers-recepcion-{$reception->id}.pdf");

    // PDF binary output starts with %PDF-
    expect($response->getContent())->toStartWith('%PDF-');
});

test('stickers view renders one 80 by 50 mm thermal sticker per serialized unit', function () {
    $html = view('pdf.stickers', [
        'stickers' => collect([
            [
                'numero_serie' => 'BF-EQ-000001',
                'marca' => 'Buckeye',
                'kilaje' => '6 kg',
                'agente' => 'PQS ABC',
                'barcode_base64' => '',
            ],
            [
                'numero_serie' => 'BF-EQ-000002',
                'marca' => '',
                'kilaje' => '',
                'agente' => '',
                'barcode_base64' => '',
            ],
        ]),
    ])->render();

    expect($html)->toContain('size: 80mm 50mm');
    expect($html)->toContain('margin: 0');
    expect(substr_count($html, 'class="sticker"'))->toBe(2);
    expect($html)->toContain('Buckeye');
    expect($html)->toContain('6 kg');
    expect($html)->toContain('PQS ABC');
    expect($html)->toContain('BF-EQ-000002');
    expect($html)->not->toContain('null');
    expect($html)->not->toContain('size: A4 portrait');
});

test('almacen user can view the stickers index listing receptions with serialized units and bulk products', function () {
    $user = almacenUserForStickersTest();
    $sede = Sede::factory()->create(['tipo' => 'almacen', 'activo' => true]);

    $prodSerial = Product::factory()->create([
        'codigo' => 'EXT-PQS-6',
        'nombre' => 'Extintor PQS 6kg',
        'serializado' => true,
    ]);
    $prodBulk = Product::factory()->create([
        'codigo' => 'MANG-15M',
        'nombre' => 'Manguera 1.5 pulg',
        'serializado' => false,
    ]);

    // Recepción CON unidades serializadas: debe aparecer en el listado.
    $receptionConStickers = Reception::create([
        'proveedor' => 'Proveedor Extintores SAC',
        'fecha' => today(),
        'sede_almacen_id' => $sede->id,
    ]);
    $unit = InventoryUnit::create([
        'product_id' => $prodSerial->id,
        'sede_almacen_id' => $sede->id,
        'numero_serie' => 'BF-EQ-000001',
        'estado' => 'disponible',
        'fecha_ingreso' => today(),
    ]);
    $movement = new InventoryMovement([
        'inventory_unit_id' => $unit->id,
        'product_id' => $prodSerial->id,
        'sede_id' => $sede->id,
        'tipo' => 'ingreso',
        'cantidad' => 1,
        'observacion' => 'Recepción con stickers',
    ]);
    $movement->referencia_type = Reception::class;
    $movement->referencia_id = $receptionConStickers->id;
    $movement->save();

    // Los productos a granel también reciben una etiqueta por producto distinto.
    $receptionSinStickers = Reception::create([
        'proveedor' => 'Proveedor Repuestos SAC',
        'fecha' => today(),
        'sede_almacen_id' => $sede->id,
    ]);
    $bulkMovement = new InventoryMovement([
        'inventory_unit_id' => null,
        'product_id' => $prodBulk->id,
        'sede_id' => $sede->id,
        'tipo' => 'ingreso',
        'cantidad' => 10,
        'observacion' => 'Recepción a granel',
    ]);
    $bulkMovement->referencia_type = Reception::class;
    $bulkMovement->referencia_id = $receptionSinStickers->id;
    $bulkMovement->save();

    $this->actingAs($user)
        ->get(route('almacen.stickers.index', ['current_team' => $user->currentTeam]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('almacen/stickers/index')
            ->has('recepciones.data', 2)
            ->where('recepciones.data.0.id', $receptionSinStickers->id)
            ->where('recepciones.data.0.productos_count', 1)
            ->where('recepciones.data.1.id', $receptionConStickers->id)
            ->where('recepciones.data.1.unidades_count', 1)
        );
});

test('stickers index route requires Almacen role', function () {
    $user = User::factory()->create();
    $user->assignRole('Vendedor');

    $this->actingAs($user)
        ->get(route('almacen.stickers.index', ['current_team' => $user->currentTeam]))
        ->assertForbidden();
});

test('stickers route requires Almacen role', function () {
    $user = User::factory()->create();
    $user->assignRole('Vendedor');
    $sede = Sede::factory()->create(['tipo' => 'almacen', 'activo' => true]);

    $reception = Reception::create([
        'proveedor' => 'Proveedor SAC',
        'fecha' => today(),
        'sede_almacen_id' => $sede->id,
    ]);

    $this->actingAs($user)
        ->get(route('almacen.recepciones.stickers', [
            'current_team' => $user->currentTeam,
            'reception' => $reception->id,
        ]))
        ->assertForbidden();
});
