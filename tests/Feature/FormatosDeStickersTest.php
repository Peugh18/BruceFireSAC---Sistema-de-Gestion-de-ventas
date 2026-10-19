<?php

use App\Models\Equipment;
use App\Models\InventoryMovement;
use App\Models\InventoryUnit;
use App\Models\Product;
use App\Models\Reception;
use App\Models\Sede;
use App\Models\ServiceOrder;
use App\Models\User;
use App\Services\Inventory\StickerPdfService;
use Database\Seeders\RolesAndPermissionsSeeder;

test('stickers conservan sus medidas y paginan diez etiquetas por hoja A4', function (string $formato, int $cantidad, int $paginas, float $ancho, float $alto) {
    $equipos = collect(range(1, $cantidad))->map(function (int $numero): Equipment {
        $equipo = new Equipment(['numero_serie' => sprintf('BF-EQ-%06d', $numero), 'marca' => 'Buckeye', 'capacidad' => '6', 'tipo_agente' => 'pqs']);
        $equipo->setRelation('product', null);

        return $equipo;
    });

    $pdf = app(StickerPdfService::class)->generateForEquipments($equipos, null, $formato);
    $pdf->output();
    $canvas = $pdf->getDomPDF()->getCanvas();

    expect($canvas->get_page_count())->toBe($paginas);
    expect($canvas->get_width())->toEqualWithDelta($ancho, 0.1);
    expect($canvas->get_height())->toEqualWithDelta($alto, 0.1);
})->with([
    'rollo de 80 por 50' => ['rollo', 2, 2, 226.77, 141.73],
    'diez en una hoja' => ['a4', 10, 1, 595.28, 841.89],
    'once en dos hojas' => ['a4', 11, 2, 595.28, 841.89],
]);

test('cada punto de impresion permite A4 y rechaza formatos desconocidos', function (string $origen) {
    $this->seed(RolesAndPermissionsSeeder::class);
    $sede = Sede::factory()->create(['tipo' => 'mixta', 'activo' => true]);
    $user = User::factory()->create();
    $user->assignRole($origen === 'taller' ? 'TecnicoPlanta' : 'Almacen');
    $product = Product::factory()->create(['serializado' => true]);
    $unit = InventoryUnit::create([
        'product_id' => $product->id, 'sede_almacen_id' => $sede->id,
        'numero_serie' => 'BF-EQ-987654', 'estado' => 'disponible', 'fecha_ingreso' => today(),
    ]);
    $reception = Reception::create(['proveedor' => 'Proveedor', 'fecha' => today(), 'sede_almacen_id' => $sede->id]);
    $movement = new InventoryMovement([
        'product_id' => $product->id, 'inventory_unit_id' => $unit->id,
        'sede_id' => $sede->id, 'tipo' => 'ingreso', 'cantidad' => 1,
    ]);
    $movement->referencia()->associate($reception);
    $movement->save();
    $order = ServiceOrder::factory()->create(['sede_id' => $sede->id]);
    $equipment = Equipment::factory()->create(['client_id' => $order->client_id]);
    $order->equipments()->attach($equipment->id);
    $parametros = ['current_team' => $user->currentTeam];
    $url = match ($origen) {
        'recepcion' => route('almacen.recepciones.stickers', $parametros + ['reception' => $reception]),
        'unidad' => route('almacen.stickers.unidad', $parametros + ['serie' => $unit->numero_serie]),
        'taller' => route('tecnico-planta.recepciones.stickers', $parametros + ['service_order' => $order]),
    };
    $separador = str_contains($url, '?') ? '&' : '?';

    $response = $this->actingAs($user)->get($url.$separador.'formato=a4');

    $response->assertOk()->assertHeader('Content-Type', 'application/pdf');
    expect($response->getContent())->toMatch('/\/MediaBox\s*\[\s*0(?:\.\d+)?\s+0(?:\.\d+)?\s+595\.\d+\s+841\.\d+\s*\]/');

    $this->getJson($url.$separador.'formato=desconocido')->assertUnprocessable()->assertJsonValidationErrors('formato');
})->with(['recepcion', 'unidad', 'taller']);
