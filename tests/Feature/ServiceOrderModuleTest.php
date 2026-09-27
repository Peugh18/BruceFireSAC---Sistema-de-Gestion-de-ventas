<?php

use App\Models\Client;
use App\Models\Sede;
use App\Models\ServiceOrder;
use App\Models\ServiceOrderEvent;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('crear service order standalone genera codigo y evento inicial', function () {
    $user = vendedorUser();
    $client = Client::factory()->create();

    $this->actingAs($user)
        ->post(route('vendedor.ordenes-servicio.store', ['current_team' => $user->currentTeam]), [
            'client_id' => $client->id,
            'tipo_servicio' => 'Recarga y mantenimiento',
            'fecha' => now()->toDateString(),
            'departamento_tecnico' => 'planta',
        ])
        ->assertSessionHasNoErrors();

    $order = ServiceOrder::firstOrFail();

    expect($order->codigo)->toBe('OT-'.now()->year.'-0001')
        ->and($order->estado)->toBe('pendiente_recepcion')
        ->and(ServiceOrderEvent::where('service_order_id', $order->id)->where('tipo', 'creada')->exists())->toBeTrue();
});

test('el seguimiento de taller agrupa por etapa las ordenes de planta y campo de la sede', function () {
    $user = vendedorUser();
    $sede = Sede::factory()->almacen()->create();
    $otraSede = Sede::factory()->almacen()->create();
    $user->update(['sede_id' => $sede->id]);
    $planta = ServiceOrder::factory()->create(['departamento_tecnico' => 'planta', 'estado' => 'pendiente_recepcion', 'sede_id' => $sede->id]);
    $campo = ServiceOrder::factory()->create(['departamento_tecnico' => 'campo', 'estado' => 'esperando_autorizacion', 'sede_id' => $sede->id]);
    $ajena = ServiceOrder::factory()->create(['estado' => 'pendiente_recepcion', 'sede_id' => $otraSede->id]);

    $props = $this->actingAs($user)
        ->get(route('vendedor.comunicacion.index', ['current_team' => $user->currentTeam, 'orden' => $campo->id]))
        ->assertOk()
        ->viewData('page')['props'];

    $columnas = collect($props['columnas'])->keyBy('clave');

    expect(collect($columnas['por_recibir']['ordenes'])->pluck('id')->all())->toBe([$planta->id])
        ->and(collect($columnas['autorizacion']['ordenes'])->pluck('id')->all())->toBe([$campo->id])
        ->and($columnas->flatMap(fn ($c) => collect($c['ordenes'])->pluck('id'))->all())->not->toContain($ajena->id)
        ->and($props['seleccionada']['id'])->toBe($campo->id)
        ->and($props['seleccionada']['etapa_actual'])->toBe(2);
});

test('el vendedor deja una nota al tecnico en la orden', function () {
    $user = vendedorUser();
    $orden = ServiceOrder::factory()->create();

    $this->actingAs($user)
        ->post(route('vendedor.comunicacion.nota', ['current_team' => $user->currentTeam, 'service_order' => $orden]), [
            'mensaje' => 'El cliente recoge el viernes.',
        ])
        ->assertRedirect();

    $evento = ServiceOrderEvent::where('service_order_id', $orden->id)->latest('id')->first();

    expect($evento->payload)->toBe(['mensaje' => 'El cliente recoge el viernes.', 'origen' => 'vendedor'])
        ->and($evento->user_id)->toBe($user->id);
});

test('la orden se asigna a un tecnico del area elegida y guarda la referencia', function () {
    $user = vendedorUser();
    $tecnicoPlanta = User::factory()->create();
    $tecnicoPlanta->assignRole('TecnicoPlanta');
    $tecnicoCampo = User::factory()->create();
    $tecnicoCampo->assignRole('TecnicoCampo');
    $datos = [
        'client_id' => Client::factory()->create()->id,
        'tipo_servicio' => 'Recarga PQS 6 kg',
        'fecha' => now()->toDateString(),
        'departamento_tecnico' => 'planta',
        'referencia' => 'PLACA: AVR-833',
    ];
    $ruta = route('vendedor.ordenes-servicio.store', ['current_team' => $user->currentTeam]);

    $this->actingAs($user)->post($ruta, [...$datos, 'tecnico_id' => $tecnicoCampo->id])
        ->assertSessionHasErrors('tecnico_id');

    $this->actingAs($user)->post($ruta, [...$datos, 'tecnico_id' => $tecnicoPlanta->id])
        ->assertSessionHasNoErrors();

    expect(ServiceOrder::firstOrFail())
        ->tecnico_id->toBe($tecnicoPlanta->id)
        ->referencia->toBe('PLACA: AVR-833');

    $this->actingAs($user)
        ->get(route('vendedor.ordenes-servicio.index', ['current_team' => $user->currentTeam]))
        ->assertInertia(fn ($page) => $page
            ->missing('clients')
            ->where('tecnicos', fn ($tecnicos) => collect($tecnicos)->pluck('departamento', 'id')->sortKeys()->all()
                === collect([$tecnicoPlanta->id => 'planta', $tecnicoCampo->id => 'campo'])->sortKeys()->all())
        );
});
