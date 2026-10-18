<?php

use App\Enums\TeamRole;
use App\Models\Client;
use App\Models\Sede;
use App\Models\Service;
use App\Models\ServiceOrder;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->team = Team::factory()->create();
    $this->sede = Sede::factory()->create();
    $this->campo = User::factory()->create(['current_team_id' => $this->team->id, 'sede_id' => $this->sede->id]);
    $this->team->members()->attach($this->campo, ['role' => TeamRole::Admin->value]);
    $this->campo->assignRole('TecnicoCampo');
    $this->service = Service::factory()->create(['nombre' => 'Recarga PQS 6 kg']);
    $this->payload = ['cliente' => ['razon_social' => 'Maria del puesto', 'telefono' => '999888777'], 'sede_id' => $this->sede->id, 'referencia' => 'Mercado Central, puesto 18', 'cantidad' => 2, 'service_id' => $this->service->id];
});

test('campo inicia su orden sin documento ni cotizacion y conserva sede y acuerdo', function () {
    $this->actingAs($this->campo)->post(route('tecnico-campo.recojos.iniciar', $this->team), [...$this->payload, 'precio_acordado' => 45])->assertRedirect();
    $order = ServiceOrder::sole();
    expect($order->client->numero_documento)->toBeNull()
        ->and($order->client->telefono)->toBe('999888777')
        ->and($order->quote_id)->toBeNull()
        ->and($order->sede_id)->toBe($this->sede->id)
        ->and($order->tecnico_id)->toBe($this->campo->id)
        ->and($order->referencia)->toBe('Mercado Central, puesto 18')
        ->and($order->lines->sole()->cantidad)->toBe(2)
        ->and($order->observaciones)->toContain('45.00');
});

test('campo inicia un recojo general sin inventar el servicio que se definira en cotizacion', function () {
    $datos = $this->payload;
    unset($datos['service_id']);

    $this->actingAs($this->campo)
        ->post(route('tecnico-campo.recojos.iniciar', $this->team), $datos)
        ->assertRedirect();

    $orden = ServiceOrder::sole();

    expect($orden->service_id)->toBeNull()
        ->and($orden->resumenServicios())->toBe('Recojo de extintores')
        ->and($orden->lines)->toBeEmpty()
        ->and($orden->events()->where('tipo', 'creada')->first()->payload['cantidad_esperada'])->toBe(2);
});

test('otro recojo reutiliza el cliente provisional y completar dni conserva su identidad', function () {
    $url = route('tecnico-campo.recojos.iniciar', $this->team);
    $this->actingAs($this->campo)->post($url, $this->payload)->assertRedirect();
    $client = Client::sole();
    $this->post($url, [...$this->payload, 'referencia' => 'Mercado Central, puesto 19'])->assertRedirect();
    $this->post($url, [...$this->payload, 'cliente' => [...$this->payload['cliente'], 'tipo_documento' => 'dni', 'numero_documento' => '12345678']])->assertRedirect();
    expect(Client::count())->toBe(1)->and($client->fresh()->numero_documento)->toBe('12345678')
        ->and(ServiceOrder::pluck('client_id')->unique()->all())->toBe([$client->id]);
});

test('el documento existente se reutiliza y el servidor ignora una sede ajena enviada por el navegador', function () {
    $client = Client::factory()->create(['tipo_documento' => 'dni', 'numero_documento' => '12345678', 'telefono' => '999111222']);
    $this->actingAs($this->campo)->post(route('tecnico-campo.recojos.iniciar', $this->team), [...$this->payload, 'cliente' => [...$this->payload['cliente'], 'numero_documento' => '12345678', 'tipo_documento' => 'dni']])->assertRedirect();
    expect(Client::count())->toBe(1)->and(ServiceOrder::sole()->client_id)->toBe($client->id)->and($client->fresh()->telefono)->toBe('999111222');
    $this->post(route('tecnico-campo.recojos.iniciar', $this->team), [...$this->payload, 'sede_id' => Sede::factory()->create()->id])->assertRedirect();
    expect(ServiceOrder::count())->toBe(2)
        ->and(ServiceOrder::query()->pluck('sede_id')->unique()->all())->toBe([$this->sede->id]);
});

test('el recojo se asigna automáticamente a la tienda del equipo y no al almacén técnico', function () {
    $almacen = Sede::factory()->mixta()->create();
    $tienda = Sede::factory()->tienda()->create(['almacen_id' => $almacen->id]);
    $this->campo->update(['sede_id' => $almacen->id]);

    $this->actingAs($this->campo)
        ->get(route('tecnico-campo.recojos.create', $this->team))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('sedes', 1)
            ->where('sedes.0.id', $tienda->id)
            ->where('sedes.0.almacen.id', $almacen->id));

    $this->post(route('tecnico-campo.recojos.iniciar', $this->team), [
        ...$this->payload,
        'sede_id' => $almacen->id,
    ])->assertRedirect();

    expect(ServiceOrder::sole()->sede_id)->toBe($tienda->id);
});

test('una cantidad digitada como texto se convierte a entero al registrar el recojo', function () {
    $this->actingAs($this->campo)
        ->post(route('tecnico-campo.recojos.iniciar', $this->team), [
            ...$this->payload,
            'cantidad' => '12',
        ])
        ->assertRedirect();

    expect(ServiceOrder::sole()->lines->sole()->cantidad)->toBe(12);
});

test('buscar por telefono y ficha reutiliza datos sin permitir modulo vendedor', function () {
    $client = Client::factory()->create(['telefono' => '999888777']);
    $this->actingAs($this->campo)->getJson(route('tecnico-campo.clientes.search', ['current_team' => $this->team, 'search' => '999888777']))->assertOk()->assertJsonPath('0.id', $client->id);
    $this->getJson(route('tecnico-campo.clientes.ficha', ['current_team' => $this->team, 'client' => $client]))->assertOk()->assertJsonPath('telefono', '999888777');
    $this->get(route('vendedor.clientes.index', $this->team))->assertForbidden();
});
