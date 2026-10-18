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
    $this->miAlmacen = Sede::factory()->almacen()->create();
    $this->otroAlmacen = Sede::factory()->almacen()->create();
    $this->almacenero = User::factory()->create(['sede_id' => $this->miAlmacen->id]);
    $this->almacenero->assignRole('Almacen');
    $producto = Product::factory()->create(['serializado' => true]);

    foreach ([[$this->miAlmacen, 'BF-EQ-100001'], [$this->otroAlmacen, 'BF-EQ-200001']] as [$sede, $serie]) {
        $recepcion = Reception::create(['proveedor' => 'Proveedor '.$serie, 'fecha' => today(), 'sede_almacen_id' => $sede->id, 'user_id' => $this->almacenero->id]);
        $unidad = InventoryUnit::create(['product_id' => $producto->id, 'sede_almacen_id' => $sede->id, 'numero_serie' => $serie, 'estado' => 'disponible', 'fecha_ingreso' => today()]);
        $movimiento = new InventoryMovement(['inventory_unit_id' => $unidad->id, 'product_id' => $producto->id, 'sede_id' => $sede->id, 'tipo' => 'ingreso', 'cantidad' => 1, 'user_id' => $this->almacenero->id]);
        $movimiento->referencia_type = Reception::class;
        $movimiento->referencia_id = $recepcion->id;
        $movimiento->save();
    }
    $this->equipo = ['current_team' => $this->almacenero->currentTeam];
});

test('los stickers solo listan las recepciones del almacén propio', function () {
    $this->actingAs($this->almacenero)
        ->get(route('almacen.stickers.index', $this->equipo))
        ->assertInertia(fn (Assert $page) => $page
            ->has('recepciones.data', 1)
            ->where('recepciones.data.0.proveedor', 'Proveedor BF-EQ-100001'));
});

test('la búsqueda rápida de series no muestra unidades de otro almacén', function () {
    $this->actingAs($this->almacenero)
        ->getJson(route('almacen.consulta.buscar', [...$this->equipo, 'q' => 'BF-EQ']))
        ->assertOk()
        ->assertJsonCount(1)
        ->assertJsonPath('0.numero_serie', 'BF-EQ-100001');
});

test('los indicadores de recepciones cuentan solo el almacén propio', function () {
    $this->actingAs($this->almacenero)
        ->get(route('almacen.recepciones.index', $this->equipo))
        ->assertInertia(fn (Assert $page) => $page
            ->where('kpis.total_recepciones', 1)
            ->where('kpis.recepciones_hoy', 1));
});

test('ingresar un lote nuevo por ajuste exige su vencimiento', function () {
    $guantes = Product::factory()->create(['serializado' => false, 'controla_lote' => true]);

    $this->actingAs($this->almacenero)
        ->post(route('almacen.ajustes.store', $this->equipo), [
            'product_id' => $guantes->id, 'sede_id' => $this->miAlmacen->id, 'tipo_ajuste' => 'incremento',
            'cantidad' => 5, 'motivo' => 'Conteo físico encontró cajas', 'lote' => 'L-77',
        ])
        ->assertSessionHasErrors('fecha_vencimiento');
});
