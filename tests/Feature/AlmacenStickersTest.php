<?php

use App\Models\InventoryMovement;
use App\Models\InventoryUnit;
use App\Models\Product;
use App\Models\Reception;
use App\Models\Sede;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

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
