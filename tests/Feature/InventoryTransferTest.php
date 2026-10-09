<?php

use App\Actions\Almacen\TransferInventory;
use App\Actions\Billing\CreateDispatchGuide;
use App\Enums\TeamRole;
use App\Models\InventoryMovement;
use App\Models\InventoryUnit;
use App\Models\Product;
use App\Models\Sede;
use App\Models\Team;
use App\Models\User;
use App\Services\Billing\GuiaRemisionPrefill;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Validation\ValidationException;
use Tests\Support\TransferGuide;

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

    TransferGuide::accepted($traslado, $this->user);
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
    TransferGuide::accepted($traslado, $this->user);
    app(TransferInventory::class)->despachar($traslado, $this->user);
    expect((int) InventoryMovement::where('product_id', $product->id)->where('sede_id', $this->destination->id)->sum('cantidad'))->toBe(0);
    $destinatario = User::factory()->create(['sede_id' => $this->destination->id]);
    $destinatario->assignRole('Almacen');
    app(TransferInventory::class)->confirmar($traslado, $destinatario);

    expect((int) InventoryMovement::where('product_id', $product->id)->sum('cantidad'))->toBe(10)
        ->and((int) InventoryMovement::where('product_id', $product->id)->where('sede_id', $this->destination->id)->sum('cantidad'))->toBe(4);
});

test('preparar traslado no mueve stock y despacho exige aceptacion con cdr', function (string $estado, ?string $cdr) {
    $unit = InventoryUnit::factory()->create(['sede_almacen_id' => $this->source->id, 'estado' => 'disponible']);
    $traslado = app(TransferInventory::class)->handle($this->source->id, ['destination_sede_id' => $this->destination->id, 'serials' => [$unit->numero_serie]], $this->user);
    expect($unit->refresh()->estado)->toBe('disponible')->and($traslado->estado)->toBe('pendiente_guia');
    if ($estado !== 'sin_guia') {
        $guia = TransferGuide::accepted($traslado, $this->user);
        $guia->update(['estado_sunat' => $estado, 'cdr_path' => $cdr]);
    }
    expect(fn () => app(TransferInventory::class)->despachar($traslado, $this->user))->toThrow(ValidationException::class);
    expect($unit->refresh()->estado)->toBe('disponible')->and($traslado->refresh()->estado)->toBe('pendiente_guia')
        ->and(InventoryMovement::where('tipo', 'traslado')->count())->toBe(0);
})->with([['sin_guia', null], ['borrador', null], ['enviada', null], ['rechazada', 'rechazada.zip'], ['aceptada', null], ['aceptada', 'cdr-inexistente.zip']]);

test('otra sede no despacha ni confirma un traslado ajeno aunque inyecte origen', function () {
    $unit = InventoryUnit::factory()->create(['sede_almacen_id' => $this->source->id, 'estado' => 'disponible']);
    $traslado = app(TransferInventory::class)->handle($this->source->id, ['destination_sede_id' => $this->destination->id, 'serials' => [$unit->numero_serie]], $this->user);
    TransferGuide::accepted($traslado, $this->user);
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
    TransferGuide::accepted($traslado, $this->user);
    app(TransferInventory::class)->despachar($traslado, $this->user);
    expect(fn () => app(TransferInventory::class)->despachar($traslado, $this->user))->toThrow(ValidationException::class);
    expect((int) InventoryMovement::where('tipo', 'traslado')->sum('cantidad'))->toBe(-1);
});

test('si el stock cambia mientras se espera cdr el despacho revierte todas sus salidas', function () {
    $product = Product::factory()->create(['serializado' => false]);
    InventoryMovement::create(['product_id' => $product->id, 'sede_id' => $this->source->id, 'tipo' => 'ingreso', 'cantidad' => 5]);
    $traslado = app(TransferInventory::class)->handle($this->source->id, ['destination_sede_id' => $this->destination->id, 'product_id' => $product->id, 'quantity' => 4], $this->user);
    TransferGuide::accepted($traslado, $this->user);
    InventoryMovement::create(['product_id' => $product->id, 'sede_id' => $this->source->id, 'tipo' => 'salida_venta', 'cantidad' => -3]);
    expect(fn () => app(TransferInventory::class)->despachar($traslado, $this->user))->toThrow(ValidationException::class);
    expect($traslado->refresh()->estado)->toBe('pendiente_guia')->and(InventoryMovement::where('tipo', 'traslado')->count())->toBe(0);
});

test('la guia del traslado conserva origen y bienes aunque el formulario inyecte otros', function () {
    $unit = InventoryUnit::factory()->create(['sede_almacen_id' => $this->source->id, 'estado' => 'disponible']);
    $traslado = app(TransferInventory::class)->handle($this->source->id, ['destination_sede_id' => $this->destination->id, 'serials' => [$unit->numero_serie]], $this->user);
    $guia = app(CreateDispatchGuide::class)->handle([
        ...app(GuiaRemisionPrefill::class)->desdeTraslado($traslado),
        'fecha_traslado' => today()->toDateString(), 'modalidad' => '01', 'peso_bruto' => 1,
        'sede_id' => $this->destination->id, 'motivo' => '01', 'items' => [['product_id' => $unit->product_id, 'descripcion' => 'Inventado', 'unidad' => 'NIU', 'cantidad' => 99]],
    ], $this->user);
    expect($guia->sede_id)->toBe($this->source->id)->and($guia->motivo)->toBe('04')->and((int) $guia->items->sum('cantidad'))->toBe(1);
});
