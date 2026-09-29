<?php

use App\Models\Product;
use App\Models\Quote;
use App\Models\Reception;
use App\Models\Sede;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function usuarioDeSede(string $rol, Sede $sede): User
{
    $usuario = User::factory()->create(['sede_id' => $sede->id]);
    $usuario->assignRole($rol);

    return $usuario;
}

test('una vendedora no puede enviar, aceptar ni rechazar cotizaciones de otra sede', function () {
    $miSede = Sede::factory()->almacen()->create();
    $otraSede = Sede::factory()->almacen()->create();
    $vendedora = usuarioDeSede('Vendedor', $miSede);
    $ajena = Quote::factory()->create(['sede_id' => $otraSede->id, 'estado' => 'borrador']);
    $team = ['current_team' => $vendedora->currentTeam];

    foreach (['send', 'accept', 'reject'] as $accion) {
        $this->actingAs($vendedora)
            ->post(route("vendedor.cotizaciones.{$accion}", [...$team, 'quote' => $ajena]))
            ->assertNotFound();
    }

    expect($ajena->fresh()->estado)->toBe('borrador');

    $propia = Quote::factory()->create(['sede_id' => $miSede->id, 'estado' => 'borrador']);
    $this->actingAs($vendedora)
        ->post(route('vendedor.cotizaciones.send', [...$team, 'quote' => $propia]))
        ->assertRedirect();
    expect($propia->fresh()->estado)->toBe('enviada');
});

test('almacen no puede ver, editar ni imprimir recepciones de otro almacen', function () {
    $miAlmacen = Sede::factory()->almacen()->create();
    $otroAlmacen = Sede::factory()->almacen()->create();
    $almacenero = usuarioDeSede('Almacen', $miAlmacen);
    $team = ['current_team' => $almacenero->currentTeam];
    $recepcion = fn (Sede $sede) => Reception::create(['proveedor' => 'PROVEEDOR SAC', 'fecha' => today(), 'sede_almacen_id' => $sede->id, 'user_id' => $almacenero->id]);
    $ajena = $recepcion($otroAlmacen);
    $item = $ajena->items()->create(['product_id' => Product::factory()->create(['serializado' => false])->id, 'cantidad' => 1, 'cantidad_conforme' => 1]);

    $this->actingAs($almacenero)->get(route('almacen.recepciones.show', [...$team, 'reception' => $ajena]))->assertNotFound();
    $this->actingAs($almacenero)->get(route('almacen.recepciones.stickers', [...$team, 'reception' => $ajena]))->assertNotFound();
    $this->actingAs($almacenero)
        ->put(route('almacen.recepciones.update', [...$team, 'reception' => $ajena]), [
            'proveedor' => 'CAMBIADO',
            'fecha' => today()->toDateString(),
            'items' => [['id' => $item->id, 'cantidad' => 1, 'cantidad_conforme' => 1]],
        ])
        ->assertNotFound();

    expect($ajena->fresh()->proveedor)->toBe('PROVEEDOR SAC');
    $this->actingAs($almacenero)->get(route('almacen.recepciones.show', [...$team, 'reception' => $recepcion($miAlmacen)]))->assertOk();
});
