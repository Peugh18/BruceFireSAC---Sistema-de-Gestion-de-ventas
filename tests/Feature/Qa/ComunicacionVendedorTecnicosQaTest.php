<?php

use App\Enums\TeamRole;
use App\Models\Client;
use App\Models\Deficiency;
use App\Models\Sede;
use App\Models\Service;
use App\Models\ServiceOrder;
use App\Models\ServiceOrderEvent;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function qaUserWithRole(string $role, ?Sede $sede = null): User
{
    $user = User::factory()->create(['sede_id' => $sede?->id]);
    $user->assignRole($role);

    return $user;
}

function qaAttachToTeam(User $user, Team $team): void
{
    if (! $team->members()->whereKey($user->id)->exists()) {
        $team->members()->attach($user, ['role' => TeamRole::Admin->value]);
    }

    $user->switchTeam($team);
}

it('permite crear una orden sin técnico y la deja visible para el área elegida', function () {
    $sede = Sede::factory()->almacen()->create();
    $vendedor = qaUserWithRole('Vendedor', $sede);
    $tecnico = qaUserWithRole('TecnicoPlanta', $sede);
    $client = Client::factory()->create();

    $this->actingAs($vendedor)
        ->post(route('vendedor.ordenes-servicio.store', ['current_team' => $vendedor->currentTeam]), [
            'client_id' => $client->id,
            'service_id' => Service::factory()->create(['nombre' => 'Recarga y mantenimiento'])->id,
            'fecha' => now()->toDateString(),
            'departamento_tecnico' => 'planta',
            'observaciones' => 'Coordinar recojo por la mañana.',
        ])
        ->assertSessionHasNoErrors();

    $order = ServiceOrder::firstOrFail();

    expect($order->tecnico_id)->toBeNull()
        ->and($order->departamento_tecnico)->toBe('planta')
        ->and($order->estado)->toBe('pendiente_recepcion');

    $this->actingAs($tecnico)
        ->get(route('tecnico-planta.recepciones.index', ['current_team' => $tecnico->currentTeam]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('orders.data', fn ($orders) => collect($orders)->contains('id', $order->id))
        );
});

it('lleva la nota del vendedor a la pantalla de ejecución de planta', function () {
    $vendedor = qaUserWithRole('Vendedor');
    $tecnico = qaUserWithRole('TecnicoPlanta');
    $order = ServiceOrder::factory()->create([
        'estado' => 'en_proceso',
        'departamento_tecnico' => 'planta',
    ]);

    $this->actingAs($vendedor)
        ->post(route('vendedor.comunicacion.nota', [
            'current_team' => $vendedor->currentTeam,
            'service_order' => $order,
        ]), ['mensaje' => 'El cliente autorizó recoger después de las 4 p. m.'])
        ->assertRedirect();

    $this->actingAs($tecnico)
        ->get(route('tecnico-planta.ejecucion.show', [
            'current_team' => $tecnico->currentTeam,
            'service_order' => $order,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('order.events', fn ($events) => collect($events)->contains(
                fn ($event) => data_get($event, 'payload.mensaje') === 'El cliente autorizó recoger después de las 4 p. m.'
            ))
        );
});

it('lleva la nota del vendedor a la pantalla de inspección de campo', function () {
    $vendedor = qaUserWithRole('Vendedor');
    $tecnico = qaUserWithRole('TecnicoCampo');
    $order = ServiceOrder::factory()->create([
        'estado' => 'en_revision',
        'departamento_tecnico' => 'campo',
    ]);

    $this->actingAs($vendedor)
        ->post(route('vendedor.comunicacion.nota', [
            'current_team' => $vendedor->currentTeam,
            'service_order' => $order,
        ]), ['mensaje' => 'Preguntar por la administradora al llegar.'])
        ->assertRedirect();

    $this->actingAs($tecnico)
        ->get(route('tecnico-campo.inspecciones.show', [
            'current_team' => $tecnico->currentTeam,
            'service_order' => $order,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('order.events', fn ($events) => collect($events)->contains(
                fn ($event) => data_get($event, 'payload.mensaje') === 'Preguntar por la administradora al llegar.'
            ))
        );
});

it('comunica una deficiencia de planta al vendedor y devuelve la autorización al técnico', function () {
    $vendedor = qaUserWithRole('Vendedor');
    $tecnico = qaUserWithRole('TecnicoPlanta');
    $order = ServiceOrder::factory()->create([
        'estado' => 'recibido_planta',
        'departamento_tecnico' => 'planta',
    ]);

    $this->actingAs($tecnico)
        ->post(route('tecnico-planta.deficiencias.store', [
            'current_team' => $tecnico->currentTeam,
            'service_order' => $order,
        ]), [
            'componente' => 'Manguera',
            'condicion' => 'Presenta fisura',
            'accion_recomendada' => 'Cambiar manguera',
            'requiere_autorizacion' => true,
            'foto' => UploadedFile::fake()->image('foto.jpg'),
        ])
        ->assertSessionHasNoErrors();

    $deficiency = Deficiency::firstOrFail();

    $this->actingAs($vendedor)
        ->post(route('vendedor.deficiencias.autorizar', [
            'current_team' => $vendedor->currentTeam,
            'deficiency' => $deficiency,
        ]), [
            'autorizado' => true,
            'autorizado_por' => 'Cliente QA',
            'canal' => 'whatsapp',
            'fecha' => now()->toDateString(),
            'observacion' => 'Aceptó el cambio y el precio.',
        ])
        ->assertSessionHasNoErrors();

    expect($deficiency->fresh()->estado)->toBe('autorizada')
        ->and($deficiency->fresh()->authorization->vendedor_id)->toBe($vendedor->id)
        ->and(ServiceOrderEvent::query()
            ->whereBelongsTo($order)
            ->where('tipo', 'notificacion_vendedor')
            ->exists())->toBeTrue()
        ->and(ServiceOrderEvent::query()
            ->whereBelongsTo($order)
            ->where('tipo', 'autorizacion_registrada')
            ->exists())->toBeTrue();

    $this->actingAs($tecnico)
        ->get(route('tecnico-planta.deficiencias.index', ['current_team' => $tecnico->currentTeam]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('deficiencies.data', fn ($items) => collect($items)->contains(
                fn ($item) => data_get($item, 'id') === $deficiency->id
                    && data_get($item, 'authorization.autorizado_por') === 'Cliente QA'
            ))
        );
});

it('impide que vendedor y técnicos ejecuten acciones del otro rol', function () {
    $vendedor = qaUserWithRole('Vendedor');
    $tecnicoPlanta = qaUserWithRole('TecnicoPlanta');
    $order = ServiceOrder::factory()->create();

    $this->actingAs($vendedor)
        ->post(route('tecnico-planta.deficiencias.store', [
            'current_team' => $vendedor->currentTeam,
            'service_order' => $order,
        ]), [
            'componente' => 'Válvula',
            'condicion' => 'Dañada',
            'foto' => UploadedFile::fake()->image('foto.jpg'),
        ])
        ->assertForbidden();

    $this->actingAs($tecnicoPlanta)
        ->post(route('vendedor.comunicacion.nota', [
            'current_team' => $tecnicoPlanta->currentTeam,
            'service_order' => $order,
        ]), ['mensaje' => 'Intento fuera de rol.'])
        ->assertForbidden();
});

it('debe sacar la orden de espera cuando el cliente rechaza el adicional', function () {
    $vendedor = qaUserWithRole('Vendedor');
    $order = ServiceOrder::factory()->create(['estado' => 'esperando_autorizacion']);
    $deficiency = Deficiency::factory()->create([
        'service_order_id' => $order->id,
        'estado' => 'esperando_autorizacion',
    ]);

    $this->actingAs($vendedor)
        ->post(route('vendedor.deficiencias.autorizar', [
            'current_team' => $vendedor->currentTeam,
            'deficiency' => $deficiency,
        ]), [
            'autorizado' => false,
            'autorizado_por' => 'Cliente QA',
            'canal' => 'whatsapp',
            'fecha' => now()->toDateString(),
        ])
        ->assertSessionHasNoErrors();

    expect($deficiency->fresh()->estado)->toBe('rechazada')
        ->and($order->fresh()->estado)->not->toBe('esperando_autorizacion');
});

it('debe ocultar al vendedor las deficiencias de otra sede', function () {
    $sede = Sede::factory()->almacen()->create();
    $otraSede = Sede::factory()->almacen()->create();
    $vendedor = qaUserWithRole('Vendedor', $sede);
    $order = ServiceOrder::factory()->create(['sede_id' => $otraSede->id]);
    $deficiency = Deficiency::factory()->create(['service_order_id' => $order->id]);

    $this->actingAs($vendedor)
        ->get(route('vendedor.deficiencias.index', ['current_team' => $vendedor->currentTeam]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('deficiencies.data', fn ($items) => collect($items)->doesntContain('id', $deficiency->id))
        );
});

it('debe impedir que un técnico opere una orden de otra sede', function () {
    $sede = Sede::factory()->almacen()->create();
    $otraSede = Sede::factory()->almacen()->create();
    $tecnico = qaUserWithRole('TecnicoPlanta', $sede);
    $order = ServiceOrder::factory()->create([
        'sede_id' => $otraSede->id,
        'departamento_tecnico' => 'planta',
    ]);

    $this->actingAs($tecnico)
        ->get(route('tecnico-planta.recepciones.show', [
            'current_team' => $tecnico->currentTeam,
            'service_order' => $order,
        ]))
        ->assertNotFound();
});
