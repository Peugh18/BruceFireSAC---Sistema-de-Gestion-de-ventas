<?php

use App\Models\Client;
use App\Models\ElectronicDocument;
use App\Models\Equipment;
use App\Models\InventoryMovement;
use App\Models\InventoryUnit;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Sede;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->sede = Sede::factory()->almacen()->create();
    $this->product = Product::factory()->create(['precio_venta' => 100]);
    $this->vendedor = User::factory()->create();
    $this->vendedor->assignRole('Vendedor');
    $this->team = ['current_team' => $this->vendedor->currentTeam];
});

function unidadDisponible(): InventoryUnit
{
    return InventoryUnit::factory()->create([
        'product_id' => test()->product->id,
        'sede_almacen_id' => test()->sede->id,
        'estado' => 'disponible',
    ]);
}

/**
 * @return array<string, mixed>
 */
function datosDeVenta(Client $client, InventoryUnit $unit, array $cambios = []): array
{
    return [
        'client_id' => $client->id,
        'sede_id' => test()->sede->id,
        'fecha' => now()->toDateString(),
        'destino' => 'local_cliente',
        'condicion_pago' => 'contado',
        'medio_pago' => 'efectivo',
        'comprobante_tipo' => 'nota_venta',
        'items' => [['tipo_linea' => 'unidad_nueva', 'numero_serie' => $unit->numero_serie, 'product_id' => test()->product->id, 'cantidad' => 1, 'precio_unitario' => 100]],
        ...$cambios,
    ];
}

test('con emitir la venta se registra y se emite en un solo paso', function () {
    $unit = unidadDisponible();

    $this->actingAs($this->vendedor)
        ->post(route('vendedor.ventas.store', $this->team), [...datosDeVenta(Client::factory()->create(), $unit), 'emitir' => true])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $sale = Sale::sole();

    expect($sale->estado)->toBe('confirmada')
        ->and($sale->numero_nota_venta)->toBe('NV-0001')
        ->and($sale->payments()->count())->toBe(1);
});

test('una venta al contado acepta el arreglo de cuotas vacio enviado por el formulario', function () {
    $unit = unidadDisponible();

    $this->actingAs($this->vendedor)
        ->post(route('vendedor.ventas.store', $this->team), [
            ...datosDeVenta(Client::factory()->create(), $unit),
            'cuotas' => [],
            'emitir' => true,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect(Sale::sole()->estado)->toBe('confirmada');
});

test('una venta a credito explica que necesita al menos una cuota', function () {
    $unit = unidadDisponible();

    $this->actingAs($this->vendedor)
        ->post(route('vendedor.ventas.store', $this->team), [
            ...datosDeVenta(Client::factory()->create(), $unit),
            'condicion_pago' => 'credito',
            'cuotas' => [],
        ])
        ->assertSessionHasErrors([
            'cuotas' => 'Define al menos una cuota para la venta a crédito.',
        ]);

    expect(Sale::count())->toBe(0);
});

test('si la emision falla no queda nada a medias y el error vuelve al formulario', function () {
    $unit = unidadDisponible();
    $conRucNoHabido = Client::factory()->create([
        'tipo_documento' => 'ruc',
        'numero_documento' => '20123456789',
        'estado_contribuyente' => 'BAJA DEFINITIVA',
        'condicion_domicilio' => 'NO HABIDO',
    ]);

    $this->actingAs($this->vendedor)
        ->post(route('vendedor.ventas.store', $this->team), [...datosDeVenta($conRucNoHabido, $unit, ['comprobante_tipo' => 'factura']), 'emitir' => true])
        ->assertSessionHasErrors('client_id');

    expect(Sale::count())->toBe(0)
        ->and($unit->fresh()->estado)->toBe('disponible')
        ->and(InventoryMovement::count())->toBe(0);
});

test('guardar borrador deja la venta sin emitir', function () {
    $this->actingAs($this->vendedor)
        ->post(route('vendedor.ventas.store', $this->team), [...datosDeVenta(Client::factory()->create(), unidadDisponible()), 'emitir' => false])
        ->assertSessionHasNoErrors();

    expect(Sale::sole()->estado)->toBe('borrador');
});

test('editar un borrador cambia cliente y extintor conservando el numero y sin ensuciar el kardex', function () {
    $primera = unidadDisponible();
    $segunda = unidadDisponible();
    $otroCliente = Client::factory()->create();

    $this->actingAs($this->vendedor)->post(route('vendedor.ventas.store', $this->team), datosDeVenta(Client::factory()->create(), $primera));
    $sale = Sale::sole();
    $equipoAnterior = $sale->items()->first()->equipment_id;

    $this->actingAs($this->vendedor)
        ->get(route('vendedor.ventas.edit', [...$this->team, 'sale' => $sale]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('vendedor/ventas/nueva')
            ->where('venta.id', $sale->id)
            ->where('venta.items.0.numero_serie', $primera->numero_serie));

    $this->actingAs($this->vendedor)
        ->put(route('vendedor.ventas.update', [...$this->team, 'sale' => $sale]), datosDeVenta($otroCliente, $segunda, ['medio_pago' => 'yape']))
        ->assertSessionHasNoErrors();

    $sale->refresh();

    expect($sale->numero_interno)->toBe('VTA-0001')
        ->and($sale->estado)->toBe('borrador')
        ->and($sale->client_id)->toBe($otroCliente->id)
        ->and($sale->medio_pago)->toBe('yape')
        ->and($primera->fresh()->estado)->toBe('disponible')
        ->and($segunda->fresh()->estado)->toBe('vendido')
        ->and(Equipment::find($equipoAnterior))->toBeNull()
        ->and(InventoryMovement::where('referencia_id', $sale->id)->pluck('inventory_unit_id')->all())->toBe([$segunda->id]);
});

test('una venta ya emitida no se edita como borrador', function () {
    $this->actingAs($this->vendedor)->post(route('vendedor.ventas.store', $this->team), [...datosDeVenta(Client::factory()->create(), unidadDisponible()), 'emitir' => true]);
    $sale = Sale::sole();

    $this->actingAs($this->vendedor)
        ->get(route('vendedor.ventas.edit', [...$this->team, 'sale' => $sale]))
        ->assertRedirect(route('vendedor.ventas.show', [...$this->team, 'sale' => $sale]));

    $this->actingAs($this->vendedor)
        ->put(route('vendedor.ventas.update', [...$this->team, 'sale' => $sale]), datosDeVenta(Client::factory()->create(), unidadDisponible()))
        ->assertSessionHasErrors('estado');
});

test('corregir productos anula el comprobante por enviar y abre una copia llena', function () {
    $unit = unidadDisponible();
    $this->actingAs($this->vendedor)->post(route('vendedor.ventas.store', $this->team), datosDeVenta(Client::factory()->create(), $unit, ['comprobante_tipo' => 'boleta']));
    $sale = Sale::sole();
    $sale->update(['estado' => 'confirmada']);
    ElectronicDocument::create(['sale_id' => $sale->id, 'tipo' => 'boleta', 'serie' => 'B001', 'correlativo' => 1, 'sunat_estado' => 'por_enviar']);

    $this->actingAs($this->vendedor)
        ->post(route('vendedor.ventas.corregir-productos', [...$this->team, 'sale' => $sale]))
        ->assertRedirect(route('vendedor.ventas.create', [...$this->team, 'rehacer' => $sale->id]));

    expect($sale->fresh()->estado)->toBe('anulada')
        ->and($unit->fresh()->estado)->toBe('disponible');

    $this->actingAs($this->vendedor)
        ->get(route('vendedor.ventas.create', [...$this->team, 'rehacer' => $sale->id]))
        ->assertInertia(fn ($page) => $page
            ->where('venta.id', null)
            ->where('venta.numero_interno', $sale->numero_interno)
            ->where('venta.comprobante_tipo', 'boleta')
            ->where('venta.items.0.numero_serie', $unit->numero_serie));
});
