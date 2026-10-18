<?php

use App\Enums\TeamRole;
use App\Models\CertificateType;
use App\Models\CertificateUnit;
use App\Models\Equipment;
use App\Models\ServiceOrder;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->team = Team::factory()->create();
    $this->tecnico = User::factory()->create(['current_team_id' => $this->team->id]);
    $this->team->members()->attach($this->tecnico, ['role' => TeamRole::Admin->value]);
    $this->tecnico->assignRole('TecnicoPlanta');
});

test('el inicio marca los mensajes nuevos de la orden y abrir el chat los deja leídos', function () {
    $order = ServiceOrder::factory()->create(['estado' => 'pendiente_recepcion']);
    $vendedor = User::factory()->create();
    $order->events()->create([
        'tipo' => 'otro',
        'user_id' => $vendedor->id,
        'payload' => ['accion' => 'mensaje', 'mensaje' => 'Llegan mañana', 'origen' => 'vendedor'],
        'created_at' => now(),
    ]);
    $inicio = route('tecnico-planta.dashboard', ['current_team' => $this->team]);

    $this->actingAs($this->tecnico)->get($inicio)->assertInertia(fn (Assert $page) => $page
        ->where('orders.data.0.no_leidos', 1)
        ->where('orders.data.0.paso.clave', 'recibir'));

    $this->post(route('ordenes.mensajes.leido', ['current_team' => $this->team, 'service_order' => $order]))->assertRedirect();

    $this->get($inicio)->assertInertia(fn (Assert $page) => $page->where('orders.data.0.no_leidos', 0));
});

test('inicio muestra hoy y atrasadas por urgencia y oculta futuras y trabajo terminado', function () {
    $this->travelTo(now()->setDate(2026, 10, 14)->setTime(9, 0));
    $normal = ServiceOrder::factory()->create(['fecha' => '2026-10-14', 'prioridad' => 'normal']);
    $urgente = ServiceOrder::factory()->create(['fecha' => '2026-10-13', 'prioridad' => 'urgente']);
    $alta = ServiceOrder::factory()->create(['fecha' => '2026-10-14', 'prioridad' => 'alta']);
    ServiceOrder::factory()->create(['fecha' => '2026-10-15', 'prioridad' => 'urgente']);
    ServiceOrder::factory()->create(['fecha' => '2026-10-13', 'estado' => 'listo_entrega']);
    ServiceOrder::factory()->create(['fecha' => '2026-10-14', 'estado' => 'trabajo_terminado']);

    $this->actingAs($this->tecnico)->get(route('tecnico-planta.dashboard', ['current_team' => $this->team]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('orders.data', 3)
            ->where('orders.data.0.id', $urgente->id)
            ->where('orders.data.1.id', $alta->id)
            ->where('orders.data.2.id', $normal->id)
            ->where('kpis.atrasadas', 1)
            ->where('filters.fecha', '2026-10-14'));
});

test('el calendario conserva la fecha en la cola y rechaza fechas invalidas', function () {
    $this->travelTo(now()->setDate(2026, 10, 14)->setTime(9, 0));
    $seleccionada = ServiceOrder::factory()->create(['fecha' => '2026-10-15']);
    ServiceOrder::factory()->create(['fecha' => '2026-10-14']);
    $url = route('tecnico-planta.dashboard', ['current_team' => $this->team]);
    $this->actingAs($this->tecnico)->get($url.'?fecha=2026-10-15')
        ->assertInertia(fn (Assert $page) => $page->has('orders.data', 1)
            ->where('orders.data.0.id', $seleccionada->id)->where('filters.fecha', '2026-10-15'));
    $this->getJson($url.'?fecha=no-es-fecha')->assertUnprocessable()->assertJsonValidationErrors('fecha');
});

test('con todo revisado se termina la orden y se emiten los certificados aunque siga en recibido', function () {
    $order = ServiceOrder::factory()->create(['estado' => 'recibido_planta']);
    $equipo = Equipment::factory()->create(['client_id' => $order->client_id, 'tipo_agente' => 'PQS ABC']);
    $order->equipments()->attach($equipo->id, ['recibido' => true]);
    CertificateType::firstOrCreate(
        ['codigo' => 'operatividad_garantia'],
        ['nombre' => 'Certificado de Operatividad y Garantía', 'vigencia_meses' => 12, 'generado_por_rol' => 'tecnico_planta']
    );
    $ruta = ['current_team' => $this->team, 'service_order' => $order];

    $this->actingAs($this->tecnico)
        ->post(route('tecnico-planta.checklist.store', [...$ruta, 'equipment' => $equipo]), ['items' => ['cilindro' => ['estado' => 'conforme']]])
        ->assertSessionHasNoErrors();
    $order->update(['estado' => 'recibido_planta']);

    $this->post(route('tecnico-planta.ejecucion.advance', $ruta), ['target_state' => 'listo_certificado'])
        ->assertSessionHasNoErrors();

    expect($order->fresh()->estado)->toBe('listo_certificado');
});

test('sin serie de fábrica o sin dirección no se emite; con los datos del aviso sí, con 600 PSI', function () {
    $order = ServiceOrder::factory()->create(['estado' => 'en_proceso']);
    $order->client->update(['direccion_fiscal' => null]);
    $equipo = Equipment::factory()->create(['client_id' => $order->client_id, 'tipo_agente' => 'PQS ABC', 'serie_fabricante' => 'No legible / Pendiente de verificar']);
    $order->equipments()->attach($equipo->id, ['recibido' => true]);
    foreach (['operatividad_garantia' => 'Certificado de Operatividad y Garantía', 'prueba_hidrostatica' => 'Certificado de Prueba Hidrostática'] as $codigo => $nombre) {
        CertificateType::firstOrCreate(['codigo' => $codigo], ['nombre' => $nombre, 'vigencia_meses' => 12, 'generado_por_rol' => 'tecnico_planta']);
    }
    $ruta = ['current_team' => $this->team, 'service_order' => $order];
    $this->actingAs($this->tecnico)
        ->post(route('tecnico-planta.checklist.store', [...$ruta, 'equipment' => $equipo]), ['items' => ['cilindro' => ['estado' => 'conforme']]]);

    $this->post(route('tecnico-planta.ejecucion.advance', $ruta), ['target_state' => 'listo_certificado'])
        ->assertSessionHasErrors('target_state');
    expect($order->fresh()->estado)->not->toBe('listo_certificado');

    $this->post(route('tecnico-planta.ejecucion.advance', $ruta), [
        'target_state' => 'listo_certificado',
        'ph_equipos' => [$equipo->id],
        'series' => [$equipo->id => 'AB-12345'],
        'direccion' => 'Av. España 1245, Trujillo',
    ])->assertSessionHasNoErrors();

    expect($order->fresh()->estado)->toBe('listo_certificado')
        ->and($equipo->fresh()->serie_fabricante)->toBe('AB-12345')
        ->and($order->client->fresh()->direccion_fiscal)->toBe('Av. España 1245, Trujillo')
        ->and(CertificateUnit::query()->whereNotNull('presion_ph')->value('presion_ph'))->toBe('600 PSI');
});
