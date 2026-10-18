<?php

use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\Reception;
use App\Models\Sede;
use App\Models\User;
use App\Services\Inventory\StickerPdfService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia as Assert;

test('la recepcion imprime una etiqueta por producto distinto aunque entren muchas unidades', function () {
    $sede = Sede::factory()->mixta()->create();
    $producto = Product::factory()->serializado(false)->create(['codigo' => 'PRD-TEST-001', 'codigo_barras' => '7759999999999', 'nombre' => 'Guante de seguridad']);
    $recepcion = Reception::create(['proveedor' => 'Proveedor', 'fecha' => today(), 'sede_almacen_id' => $sede->id]);
    foreach ([15, 25] as $cantidad) {
        $movimiento = new InventoryMovement(['product_id' => $producto->id, 'sede_id' => $sede->id, 'tipo' => 'ingreso', 'cantidad' => $cantidad]);
        $movimiento->referencia()->associate($recepcion);
        $movimiento->save();
    }

    $pdf = app(StickerPdfService::class)->generate($recepcion);
    $html = $pdf->getDomPDF()->getDom()->saveHTML();
    $pdf->output();

    expect(substr_count($html, 'class="sticker"'))->toBe(1);
    expect($html)->toContain('PRD-TEST-001')->toContain('Guante de seguridad')->not->toContain('7759999999999');
    expect($pdf->getDomPDF()->getCanvas()->get_page_count())->toBe(1);
    expect($producto->fresh()->codigo_barras)->toBe('7759999999999');
});

test('la lista de stickers incluye recepciones con productos sin serie y cuenta productos distintos', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $sede = Sede::factory()->mixta()->create();
    $user = User::factory()->create(['sede_id' => $sede->id]);
    $user->assignRole('Almacen');
    $producto = Product::factory()->serializado(false)->create();
    $recepcion = Reception::create(['proveedor' => 'Guantes SAC', 'fecha' => today(), 'sede_almacen_id' => $sede->id]);
    foreach ([10, 20] as $cantidad) {
        $movimiento = new InventoryMovement(['product_id' => $producto->id, 'sede_id' => $sede->id, 'tipo' => 'ingreso', 'cantidad' => $cantidad]);
        $movimiento->referencia()->associate($recepcion);
        $movimiento->save();
    }

    $this->actingAs($user)->get(route('almacen.stickers.index', ['current_team' => $user->currentTeam]))
        ->assertInertia(fn (Assert $page) => $page->component('almacen/stickers/index')
            ->has('recepciones.data', 1)->where('recepciones.data.0.id', $recepcion->id)
            ->where('recepciones.data.0.productos_count', 1));
});

test('almacen reimprime el codigo interno del producto y valida el formato', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $sede = Sede::factory()->mixta()->create();
    $user = User::factory()->create(['sede_id' => $sede->id]);
    $user->assignRole('Almacen');
    $producto = Product::factory()->serializado(false)->create(['codigo_barras' => null]);
    $url = route('almacen.stickers.index', ['current_team' => $user->currentTeam]).'/producto';

    $this->actingAs($user)->get($url.'?codigo='.$producto->codigo.'&formato=a4')
        ->assertOk()->assertHeader('Content-Type', 'application/pdf');
    $this->getJson($url.'?codigo='.$producto->codigo.'&formato=otro')
        ->assertUnprocessable()->assertJsonValidationErrors('formato');
    expect($producto->fresh()->codigo_barras)->toBeNull();
});

test('vendedor no puede imprimir stickers de productos', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $user = User::factory()->create();
    $user->assignRole('Vendedor');
    $producto = Product::factory()->serializado(false)->create();
    $url = route('almacen.stickers.index', ['current_team' => $user->currentTeam]).'/producto';

    $this->actingAs($user)->get($url.'?codigo='.$producto->codigo)->assertForbidden();
});

test('extintores serializados requieren su etiqueta individual y no la etiqueta de producto', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $sede = Sede::factory()->mixta()->create();
    $user = User::factory()->create(['sede_id' => $sede->id]);
    $user->assignRole('Almacen');
    $producto = Product::factory()->serializado()->create();
    $url = route('almacen.stickers.index', ['current_team' => $user->currentTeam]).'/producto';

    $this->actingAs($user)->get($url.'?codigo='.$producto->codigo)->assertNotFound();
});
