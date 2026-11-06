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
    $this->cliente = Client::factory()->create(['razon_social' => 'Maria del puesto', 'telefono' => '999888777']);
    $this->payload = ['client_id' => $this->cliente->id, 'sede_id' => $this->sede->id, 'referencia' => 'Mercado Central, puesto 18', 'cantidad' => 2, 'service_id' => $this->service->id];
});

test('campo inicia su orden sin cotizacion y conserva cliente, sede y acuerdo', function () {
    $this->actingAs($this->campo)->post(route('tecnico-campo.recojos.iniciar', $this->team), [...$this->payload, 'precio_acordado' => 45])->assertRedirect();
    $order = ServiceOrder::sole();
    expect($order->client_id)->toBe($this->cliente->id)
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

test('sin cliente elegido el recojo no se inicia', function () {
    $datos = $this->payload;
    unset($datos['client_id']);

    $this->actingAs($this->campo)->post(route('tecnico-campo.recojos.iniciar', $this->team), $datos)
        ->assertSessionHasErrors(['client_id' => 'Elige al cliente o regístralo con «Agregar cliente».']);
    expect(ServiceOrder::count())->toBe(0);
});

test('campo registra al cliente con el alta completa de Clientes', function () {
    $url = route('tecnico-campo.clientes.store', $this->team);

    $this->actingAs($this->campo)->postJson($url, ['razon_social' => 'Bodega Sin Documento', 'telefono' => '999000111'])
        ->assertJsonValidationErrors(['tipo_documento', 'numero_documento']);

    $this->postJson($url, ['tipo_documento' => 'dni', 'numero_documento' => '40405210', 'razon_social' => 'YOBANY ZULY GUEVARA CABRERA', 'telefono' => '959166911'])
        ->assertCreated()
        ->assertJsonPath('numero_documento', '40405210');
    expect(Client::where('numero_documento', '40405210')->sole()->telefono)->toBe('959166911');
});

test('el servidor ignora una sede ajena enviada por el navegador', function () {
    $this->actingAs($this->campo)->post(route('tecnico-campo.recojos.iniciar', $this->team), $this->payload)->assertRedirect();
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
    $this->actingAs($this->campo)->getJson(route('tecnico-campo.clientes.search', ['current_team' => $this->team, 'search' => '999888777']))->assertOk()->assertJsonFragment(['id' => $client->id]);
    $this->getJson(route('tecnico-campo.clientes.ficha', ['current_team' => $this->team, 'client' => $client]))->assertOk()->assertJsonPath('telefono', '999888777');
    $this->get(route('vendedor.clientes.index', $this->team))->assertForbidden();
});
