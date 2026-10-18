<?php

use App\Models\Deficiency;
use App\Models\ServiceOrder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(fn () => $this->seed(RolesAndPermissionsSeeder::class));

test('la ficha de la orden trae sus hallazgos para decidir el adicional ahí mismo', function () {
    $vendedor = vendedorUser();
    $orden = ServiceOrder::factory()->create(['sede_id' => $vendedor->sede_id, 'estado' => 'esperando_autorizacion']);
    $propia = Deficiency::factory()->create(['service_order_id' => $orden->id, 'estado' => 'esperando_autorizacion', 'componente' => 'Válvula']);
    Deficiency::factory()->create(['estado' => 'esperando_autorizacion', 'componente' => 'Manguera ajena']);

    $this->actingAs($vendedor)
        ->get(route('vendedor.ordenes-servicio.show', ['current_team' => $vendedor->currentTeam, 'service_order' => $orden]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('deficiencias', 1)
            ->where('deficiencias.0.id', $propia->id)
            ->where('deficiencias.0.componente', 'Válvula')
            ->where('deficiencias.0.estado', 'esperando_autorizacion')
        );
});

test('la ficha de la orden muestra el mismo paso que ve el taller y el chat de la orden', function () {
    $vendedor = vendedorUser();
    $orden = ServiceOrder::factory()->create(['sede_id' => $vendedor->sede_id, 'estado' => 'esperando_autorizacion']);
    Deficiency::factory()->create(['service_order_id' => $orden->id, 'estado' => 'esperando_autorizacion']);
    $orden->events()->create(['tipo' => 'otro', 'payload' => ['accion' => 'mensaje', 'mensaje' => 'Revisa la base', 'origen' => 'planta']]);

    $this->actingAs($vendedor)
        ->get(route('vendedor.ordenes-servicio.show', ['current_team' => $vendedor->currentTeam, 'service_order' => $orden]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('paso.numero', 2)
            ->where('conversacion.eventos.0.mensaje', 'Revisa la base')
            ->where('conversacion.no_leidos', 1)
        );
});
