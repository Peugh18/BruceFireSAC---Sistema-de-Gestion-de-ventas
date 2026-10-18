<?php

use App\Actions\Almacen\TransferInventory;
use App\Enums\TeamRole;
use App\Models\InventoryMovement;
use App\Models\InventoryUnit;
use App\Models\Product;
use App\Models\Sede;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->team = Team::factory()->create();
    $this->source = Sede::factory()->create(['tipo' => 'almacen', 'activo' => true]);
    $this->destination = Sede::factory()->create(['tipo' => 'mixta', 'activo' => true]);
    $this->user = User::factory()->create(['current_team_id' => $this->team->id, 'sede_id' => $this->source->id]);
    $this->team->members()->attach($this->user, ['role' => TeamRole::Admin->value]);
    $this->user->assignRole('Almacen');
});

test('traslado serializado registra salida entrada y mueve la unidad atomicamente', function () {
    $unit = InventoryUnit::factory()->create(['sede_almacen_id' => $this->source->id, 'estado' => 'disponible']);

    $traslado = app(TransferInventory::class)->handle($this->source->id, ['destination_sede_id' => $this->destination->id, 'serials' => [$unit->numero_serie]], $this->user);

    app(TransferInventory::class)->despachar($traslado, $this->user);

    // Fase H: queda en tránsito hasta que el destino confirma la llegada.
    expect($unit->refresh()->estado)->toBe('en_transito')->and($unit->sede_almacen_id)->toBe($this->source->id);
    $destinatario = User::factory()->create(['sede_id' => $this->destination->id]);
    $destinatario->assignRole('Almacen');
    app(TransferInventory::class)->confirmar($traslado, $destinatario);

    expect($unit->refresh()->sede_almacen_id)->toBe($this->destination->id)
        ->and(InventoryMovement::where('inventory_unit_id', $unit->id)->where('tipo', 'traslado')->pluck('cantidad')->sort()->values()->all())->toBe([-1, 1]);
});

test('traslado de producto sin serie conserva el saldo total', function () {
    $product = Product::factory()->create(['serializado' => false]);
    InventoryMovement::create(['product_id' => $product->id, 'sede_id' => $this->source->id, 'tipo' => 'ingreso', 'cantidad' => 10, 'user_id' => $this->user->id]);

    $traslado = app(TransferInventory::class)->handle($this->source->id, ['destination_sede_id' => $this->destination->id, 'product_id' => $product->id, 'quantity' => 4], $this->user);
    app(TransferInventory::class)->despachar($traslado, $this->user);
    expect((int) InventoryMovement::where('product_id', $product->id)->where('sede_id', $this->destination->id)->sum('cantidad'))->toBe(0);
    $destinatario = User::factory()->create(['sede_id' => $this->destination->id]);
    $destinatario->assignRole('Almacen');
    app(TransferInventory::class)->confirmar($traslado, $destinatario);

    expect((int) InventoryMovement::where('product_id', $product->id)->sum('cantidad'))->toBe(10)
        ->and((int) InventoryMovement::where('product_id', $product->id)->where('sede_id', $this->destination->id)->sum('cantidad'))->toBe(4);
});

test('preparar traslado no mueve stock y se puede despachar sin GRE', function () {
    $unit = InventoryUnit::factory()->create(['sede_almacen_id' => $this->source->id, 'estado' => 'disponible']);
    $traslado = app(TransferInventory::class)->handle($this->source->id, ['destination_sede_id' => $this->destination->id, 'serials' => [$unit->numero_serie]], $this->user);
    expect($unit->refresh()->estado)->toBe('disponible')->and($traslado->estado)->toBe('pendiente_despacho');

    app(TransferInventory::class)->despachar($traslado, $this->user);

    expect($unit->refresh()->estado)->toBe('en_transito')
        ->and($traslado->refresh()->estado)->toBe('en_transito')
        ->and((int) InventoryMovement::where('tipo', 'traslado')->sum('cantidad'))->toBe(-1);
});

test('otra sede no despacha ni confirma un traslado ajeno aunque inyecte origen', function () {
    $unit = InventoryUnit::factory()->create(['sede_almacen_id' => $this->source->id, 'estado' => 'disponible']);
    $traslado = app(TransferInventory::class)->handle($this->source->id, ['destination_sede_id' => $this->destination->id, 'serials' => [$unit->numero_serie]], $this->user);
    $ajeno = User::factory()->create(['sede_id' => Sede::factory()->almacen()->create()->id]);
    $ajeno->assignRole('Almacen');
    $this->actingAs($ajeno)->post(route('almacen.traslados.despachar', ['current_team' => $ajeno->currentTeam, 'traslado' => $traslado]), ['origen_sede_id' => $this->source->id])->assertForbidden();
    $this->actingAs($ajeno)->post(route('almacen.traslados.confirmar', ['current_team' => $ajeno->currentTeam, 'traslado' => $traslado]))->assertForbidden();
    expect($unit->refresh()->estado)->toBe('disponible');
});

test('no recibe antes del despacho ni permite despachar dos veces', function () {
    $unit = InventoryUnit::factory()->create(['sede_almacen_id' => $this->source->id, 'estado' => 'disponible']);
    $traslado = app(TransferInventory::class)->handle($this->source->id, ['destination_sede_id' => $this->destination->id, 'serials' => [$unit->numero_serie]], $this->user);
    $destino = User::factory()->create(['sede_id' => $this->destination->id]);
    $destino->assignRole('Almacen');
    expect(fn () => app(TransferInventory::class)->confirmar($traslado, $destino))->toThrow(ValidationException::class);
    app(TransferInventory::class)->despachar($traslado, $this->user);
    expect(fn () => app(TransferInventory::class)->despachar($traslado, $this->user))->toThrow(ValidationException::class);
    expect((int) InventoryMovement::where('tipo', 'traslado')->sum('cantidad'))->toBe(-1);
});

test('si el stock cambia mientras se espera cdr el despacho revierte todas sus salidas', function () {
    $product = Product::factory()->create(['serializado' => false]);
    InventoryMovement::create(['product_id' => $product->id, 'sede_id' => $this->source->id, 'tipo' => 'ingreso', 'cantidad' => 5]);
    $traslado = app(TransferInventory::class)->handle($this->source->id, ['destination_sede_id' => $this->destination->id, 'product_id' => $product->id, 'quantity' => 4], $this->user);
    InventoryMovement::create(['product_id' => $product->id, 'sede_id' => $this->source->id, 'tipo' => 'salida_venta', 'cantidad' => -3]);
    expect(fn () => app(TransferInventory::class)->despachar($traslado, $this->user))->toThrow(ValidationException::class);
    expect($traslado->refresh()->estado)->toBe('pendiente_despacho')->and(InventoryMovement::where('tipo', 'traslado')->count())->toBe(0);
});

test('listado de traslados permite despachar sin datos de GRE', function () {
    $unit = InventoryUnit::factory()->create(['sede_almacen_id' => $this->source->id, 'estado' => 'disponible']);
    $transfer = app(TransferInventory::class)->handle($this->source->id, ['destination_sede_id' => $this->destination->id, 'serials' => [$unit->numero_serie]], $this->user);
    $this->actingAs($this->user)->get(route('almacen.traslados.index', ['current_team' => $this->team]))->assertOk();
    $this->actingAs($this->user)->get(route('almacen.traslados.index', ['current_team' => $this->team]))->assertOk()->assertInertia(fn ($page) => $page
        ->where('enTransito.0.puede_despachar', true)
        ->missing('enTransito.0.guia_aceptada')
    );
});
