<?php

use App\Models\Marca;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->vendedor = User::factory()->create();
    $this->vendedor->assignRole('Vendedor');
});

test('se agrega una marca y sale en los formularios', function () {
    $this->actingAs($this->vendedor)
        ->postJson(route('marcas.store'), ['nombre' => '  Badger  '])
        ->assertCreated();

    expect(Marca::nombresActivos())->toContain('Badger');
});

test('no se repite una marca y volver a agregar una quitada la reactiva', function () {
    $marca = Marca::create(['nombre' => 'Amerex', 'activo' => true]);

    $this->actingAs($this->vendedor)->postJson(route('marcas.store'), ['nombre' => 'amerex'])->assertUnprocessable();
    $this->deleteJson(route('marcas.destroy', $marca))->assertOk();
    $this->postJson(route('marcas.store'), ['nombre' => 'amerex'])->assertCreated();

    expect(Marca::query()->where('nombre', 'Amerex')->sole()->activo)->toBeTrue()
        ->and(Marca::query()->count())->toBe(1);
});

test('renombrar una marca no deja duplicados', function () {
    $kidde = Marca::create(['nombre' => 'Kidde', 'activo' => true]);
    Marca::create(['nombre' => 'Badger', 'activo' => true]);

    $this->actingAs($this->vendedor)->patchJson(route('marcas.update', $kidde), ['nombre' => 'Badger'])->assertUnprocessable();
    $this->patchJson(route('marcas.update', $kidde), ['nombre' => 'Kidde Fenwal'])->assertOk();

    expect($kidde->fresh()->nombre)->toBe('Kidde Fenwal');
});
