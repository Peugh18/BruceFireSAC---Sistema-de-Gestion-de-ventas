<?php

use App\Models\InventoryTransfer;
use App\Models\InventoryUnit;
use App\Models\Sede;
use App\Models\ServiceOrder;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->gerente = User::factory()->create();
    $this->gerente->assignRole('Gerente');
    $this->almacen = Sede::factory()->almacen()->create();
});

test('convertir el almacen legacy en taller conserva sus ordenes en la cola tecnica', function () {
    $orden = ServiceOrder::factory()->create(['sede_id' => $this->almacen->id, 'tecnico_id' => null]);
    $tecnico = User::factory()->create(['sede_id' => $this->almacen->id]);
    $tecnico->assignRole('TecnicoPlanta');
    $this->actingAs($this->gerente)->put(route('gerente.sedes.update', ['current_team' => $this->gerente->currentTeam, 'sede' => $this->almacen]), [
        'nombre' => $this->almacen->nombre, 'tipo' => 'taller', 'activo' => true,
    ])->assertSessionHasNoErrors();

    expect($this->almacen->fresh()->tipo)->toBe('taller');
    expect(ServiceOrder::query()->accessibleToTechnician($tecnico)->pluck('id')->all())->toContain($orden->id);
    expect($orden->atendiblePor($tecnico))->toBeTrue();
});

test('inventario comprometido impide convertir un almacen en taller', function (string $estado) {
    InventoryUnit::factory()->create(['sede_almacen_id' => $this->almacen->id, 'estado' => $estado]);
    $this->actingAs($this->gerente)->put(route('gerente.sedes.update', ['current_team' => $this->gerente->currentTeam, 'sede' => $this->almacen]), [
        'nombre' => $this->almacen->nombre, 'tipo' => 'taller', 'activo' => true,
    ])->assertSessionHasErrors('tipo');
    expect($this->almacen->fresh()->tipo)->toBe('almacen');
})->with(['reservado', 'en_transito']);

test('inventario comprometido impide desactivar un almacen', function (string $estado) {
    InventoryUnit::factory()->create(['sede_almacen_id' => $this->almacen->id, 'estado' => $estado]);
    $this->actingAs($this->gerente)->patch(route('gerente.sedes.toggle-status', ['current_team' => $this->gerente->currentTeam, 'sede' => $this->almacen]))
        ->assertSessionHas('error');
    expect($this->almacen->fresh()->activo)->toBeTrue();
})->with(['reservado', 'en_transito']);

test('un traslado pendiente protege tanto origen como destino de conversion y desactivacion', function (string $estado, bool $esOrigen) {
    $otraSede = Sede::factory()->almacen()->create();
    InventoryTransfer::query()->create([
        'origen_sede_id' => $esOrigen ? $this->almacen->id : $otraSede->id,
        'destino_sede_id' => $esOrigen ? $otraSede->id : $this->almacen->id,
        'user_id' => $this->gerente->id, 'estado' => $estado,
    ]);
    $params = ['current_team' => $this->gerente->currentTeam, 'sede' => $this->almacen];
    $this->actingAs($this->gerente)->put(route('gerente.sedes.update', $params), [
        'nombre' => $this->almacen->nombre, 'tipo' => 'taller', 'activo' => true,
    ])->assertSessionHasErrors('tipo');
    expect($this->almacen->fresh()->tipo)->toBe('almacen');
    $this->patch(route('gerente.sedes.toggle-status', $params))->assertSessionHas('error');
    expect($this->almacen->fresh()->activo)->toBeTrue();
})->with([
    'origen pendiente' => [InventoryTransfer::PENDIENTE_DESPACHO, true],
    'destino pendiente' => [InventoryTransfer::PENDIENTE_DESPACHO, false],
    'origen en transito' => [InventoryTransfer::EN_TRANSITO, true],
    'destino en transito' => [InventoryTransfer::EN_TRANSITO, false],
]);

test('el historial de traslados ya recibidos no impide convertir ni desactivar una sede sin stock', function () {
    InventoryTransfer::query()->create([
        'origen_sede_id' => $this->almacen->id,
        'destino_sede_id' => Sede::factory()->almacen()->create()->id,
        'user_id' => $this->gerente->id, 'estado' => InventoryTransfer::RECIBIDO,
    ]);
    $params = ['current_team' => $this->gerente->currentTeam, 'sede' => $this->almacen];
    $this->actingAs($this->gerente)->put(route('gerente.sedes.update', $params), [
        'nombre' => $this->almacen->nombre, 'tipo' => 'taller', 'activo' => true,
    ])->assertSessionHasNoErrors();
    expect($this->almacen->fresh()->tipo)->toBe('taller');
    $this->patch(route('gerente.sedes.toggle-status', $params))->assertSessionMissing('error');
    expect($this->almacen->fresh()->activo)->toBeFalse();
});
