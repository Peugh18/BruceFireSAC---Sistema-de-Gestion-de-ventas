<?php

use App\Models\Client;
use App\Models\Sede;
use App\Models\ServiceOrder;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

// P-013: el recojo de campo nace sin servicio y rompía el seguimiento de taller.
it('muestra el seguimiento de taller aunque una orden de recojo no tenga servicio', function () {
    $sede = Sede::factory()->almacen()->create();
    $vendedor = User::factory()->create(['sede_id' => $sede->id]);
    $vendedor->assignRole('Vendedor');

    ServiceOrder::factory()->create([
        'client_id' => Client::factory()->create()->id,
        'sede_id' => $sede->id,
        'service_id' => null,
        'departamento_tecnico' => 'campo',
        'estado' => 'pendiente_recepcion',
    ]);

    $this->actingAs($vendedor)
        ->get(route('vendedor.comunicacion.index', ['current_team' => $vendedor->currentTeam]))
        ->assertOk();
});

// P-017: ningún mensaje de validación debe mostrar el nombre técnico del campo.
it('traduce los nombres de campo que aparecían en jerga', function (string $campo, string $esperado) {
    $mensaje = validator([$campo => null], [$campo => 'required'])->errors()->first($campo);

    expect($mensaje)->toContain($esperado)
        ->and($mensaje)->not->toContain(str_replace('_', ' ', $campo));
})->with([
    ['service_id', 'servicio'],
    ['anio_fabricacion', 'año de fabricación'],
    ['product_id', 'producto'],
    ['equipment_id', 'extintor'],
    ['marca_id', 'marca'],
]);
