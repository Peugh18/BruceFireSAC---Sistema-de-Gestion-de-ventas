<?php

use App\Models\InventoryMovement;
use App\Models\InventoryUnit;
use App\Models\Product;
use App\Models\Sede;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

if (! function_exists('almacenUserForAdjustmentTest')) {
    function almacenUserForAdjustmentTest(): User
    {
        $user = User::factory()->create();
        $user->assignRole('Almacen');

        return $user;
    }
}

test('almacen user can view ajustes index page', function () {
    $user = almacenUserForAdjustmentTest();
    $sede = Sede::factory()->create(['tipo' => 'almacen', 'activo' => true]);

    $this->actingAs($user)
        ->get(route('almacen.ajustes.index', ['current_team' => $user->currentTeam]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('almacen/ajustes/index')
            ->has('ajustes')
            ->has('sedes')
            ->has('products')
            ->has('kpis')
        );
});

test('almacen user can create positive stock adjustment without editing stock directly', function () {
    $user = almacenUserForAdjustmentTest();
    $sede = Sede::factory()->create(['tipo' => 'almacen', 'activo' => true]);

    $prod = Product::factory()->create([
        'codigo' => 'MANG-FIRE',
        'nombre' => 'Manguera contra incendio',
        'serializado' => false,
    ]);

    $payload = [
        'product_id' => $prod->id,
        'sede_id' => $sede->id,
        'tipo_ajuste' => 'incremento',
        'cantidad' => 5,
        'motivo' => 'Sobrante detectado en conteo físico semestral',
        'observacion' => 'Acta de inventario #042',
    ];

    $this->actingAs($user)
        ->post(route('almacen.ajustes.store', ['current_team' => $user->currentTeam]), $payload)
        ->assertRedirect(route('almacen.ajustes.index', ['current_team' => $user->currentTeam]))
        ->assertSessionHasNoErrors();

    // Verify movement in Kardex
    $movement = InventoryMovement::where('product_id', $prod->id)
        ->where('tipo', 'ajuste')
        ->first();

    expect($movement)->not->toBeNull()
        ->and($movement->cantidad)->toBe(5)
        ->and($movement->user_id)->toBe($user->id)
        ->and($movement->observacion)->toContain('Sobrante detectado')
        ->and($movement->observacion)->toContain('Acta de inventario #042');
});

test('negative adjustment on serial unit marks unit as baja and creates negative movement', function () {
    $user = almacenUserForAdjustmentTest();
    $sede = Sede::factory()->create(['tipo' => 'almacen', 'activo' => true]);

    $prod = Product::factory()->create([
        'codigo' => 'EXT-10',
        'nombre' => 'Extintor 10kg',
        'serializado' => true,
    ]);

    $unit = InventoryUnit::create([
        'product_id' => $prod->id,
        'sede_almacen_id' => $sede->id,
        'numero_serie' => 'BF-EQ-009999',
        'marca' => 'Kidde',
        'estado' => 'disponible',
        'fecha_ingreso' => today(),
    ]);

    $payload = [
        'product_id' => $prod->id,
        'sede_id' => $sede->id,
        'inventory_unit_id' => $unit->id,
        'tipo_ajuste' => 'decremento',
        'cantidad' => 1,
        'motivo' => 'Cuerpo deformado por caída de montacargas en pasillo 3',
    ];

    $this->actingAs($user)
        ->post(route('almacen.ajustes.store', ['current_team' => $user->currentTeam]), $payload)
        ->assertSessionHasNoErrors();

    // Verify movement
    $movement = InventoryMovement::where('inventory_unit_id', $unit->id)
        ->where('tipo', 'ajuste')
        ->first();

    expect($movement)->not->toBeNull()
        ->and($movement->cantidad)->toBe(-1)
        ->and($movement->observacion)->toContain('Cuerpo deformado por caída');

    // Verify unit state is now 'baja'
    $unit->refresh();
    expect($unit->estado)->toBe('baja');
});

test('motivo is mandatory and requires minimum 10 characters', function () {
    $user = almacenUserForAdjustmentTest();
    $sede = Sede::factory()->create(['tipo' => 'almacen', 'activo' => true]);
    $prod = Product::factory()->create(['serializado' => false]);

    // Short motivo (< 10 chars)
    $this->actingAs($user)
        ->post(route('almacen.ajustes.store', ['current_team' => $user->currentTeam]), [
            'product_id' => $prod->id,
            'sede_id' => $sede->id,
            'tipo_ajuste' => 'decremento',
            'cantidad' => 2,
            'motivo' => 'Falla',
        ])
        ->assertSessionHasErrors(['motivo']);
});

test('non almacen user cannot access stock adjustments', function () {
    $user = User::factory()->create();
    $user->assignRole('Vendedor');

    $this->actingAs($user)
        ->get(route('almacen.ajustes.index', ['current_team' => $user->currentTeam]))
        ->assertForbidden();
});
