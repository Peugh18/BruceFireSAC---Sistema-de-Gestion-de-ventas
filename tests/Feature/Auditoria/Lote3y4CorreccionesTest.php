<?php

use App\Models\CashRegister;
use App\Models\Client;
use App\Models\Deficiency;
use App\Models\Equipment;
use App\Models\Sede;
use App\Models\ServiceOrder;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

// P-006: el Gerente da por revisada la diferencia del cierre, con su comentario.
it('el gerente marca revisada la diferencia de un cierre de caja', function () {
    $gerente = User::factory()->create();
    $gerente->assignRole('Gerente');
    $caja = CashRegister::factory()->cerrado()->create(['monto_contado_cierre' => 95]);
    $team = ['current_team' => $gerente->currentTeam, 'cash_register' => $caja];

    $this->actingAs($gerente)->post(route('gerente.cajas.revisar', $team), [])
        ->assertSessionHasErrors('revision_nota');

    $this->actingAs($gerente)->post(route('gerente.cajas.revisar', $team), ['revision_nota' => 'Vuelto mal dado; se descuenta.'])
        ->assertSessionHasNoErrors();

    $caja->refresh();
    expect($caja->revisada_por)->toBe($gerente->id)
        ->and($caja->revision_nota)->toBe('Vuelto mal dado; se descuenta.')
        ->and($caja->revisada_at)->not->toBeNull();
});

// P-012: la pestaña «Recibidas» cuenta lo mismo que muestra la lista.
it('el contador de recibidas de planta coincide con su lista', function () {
    $sede = Sede::factory()->almacen()->create();
    $tecnico = User::factory()->create(['sede_id' => $sede->id]);
    $tecnico->assignRole('TecnicoPlanta');
    $otraSede = Sede::factory()->almacen()->create();
    ServiceOrder::factory()->create(['sede_id' => $otraSede->id, 'departamento_tecnico' => 'planta', 'estado' => 'recibido_planta']);

    $this->actingAs($tecnico)
        ->get(route('tecnico-planta.recepciones.index', ['current_team' => $tecnico->currentTeam, 'filter' => 'recibidas']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('counts.recibidas', fn ($total) => (int) $total === count($page->toArray()['props']['orders']['data'])));
});

// P-030: reparar sin repuesto exige decir qué se hizo.
it('reparar sin repuesto pide qué se hizo', function () {
    $sede = Sede::factory()->almacen()->create();
    $tecnico = User::factory()->create(['sede_id' => $sede->id]);
    $tecnico->assignRole('TecnicoPlanta');
    $orden = ServiceOrder::factory()->create(['sede_id' => $sede->id, 'client_id' => Client::factory(), 'departamento_tecnico' => 'planta', 'estado' => 'en_proceso', 'tecnico_id' => $tecnico->id]);
    $deficiencia = Deficiency::factory()->create([
        'service_order_id' => $orden->id, 'equipment_id' => Equipment::factory()->create(['client_id' => $orden->client_id])->id,
        'estado' => 'detectada', 'requiere_autorizacion' => false, 'repuesto_sugerido' => null,
    ]);

    $this->actingAs($tecnico)
        ->post(route('tecnico-planta.deficiencias.resolve', ['current_team' => $tecnico->currentTeam, 'deficiency' => $deficiencia]), ['resolucion' => 'ok'])
        ->assertSessionHasErrors('resolucion');

    expect($deficiencia->fresh()->estado)->toBe('detectada');
});
