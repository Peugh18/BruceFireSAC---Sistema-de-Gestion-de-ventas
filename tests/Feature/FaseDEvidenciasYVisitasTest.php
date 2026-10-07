<?php

use App\Enums\TeamRole;
use App\Models\Deficiency;
use App\Models\Equipment;
use App\Models\Evidencia;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Sede;
use App\Models\Service;
use App\Models\ServiceOrder;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\CertificateTypeSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

const FIRMA_PNG = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

function usuarioConRol(string $rol, Team $team, array $extra = []): User
{
    $user = User::factory()->create(['current_team_id' => $team->id, ...$extra]);
    $team->members()->attach($user, ['role' => TeamRole::Admin->value]);
    $user->assignRole($rol);

    return $user;
}

beforeEach(function () {
    Storage::fake('local');
    $this->seed([RolesAndPermissionsSeeder::class, CertificateTypeSeeder::class]);
    $this->team = Team::factory()->create();
    $this->sede = Sede::factory()->create();
    $this->orden = ServiceOrder::factory()->create(['sede_id' => $this->sede->id, 'departamento_tecnico' => 'campo', 'estado' => 'en_revision']);
    $this->equipo = Equipment::factory()->create(['client_id' => $this->orden->client_id, 'estado' => 'operativo']);
    $this->orden->equipments()->attach($this->equipo->id, ['recibido' => true]);
    $this->url = fn (string $nombre, array $extra = []) => route($nombre, ['current_team' => $this->team, 'service_order' => $this->orden, ...$extra]);
});

test('vendedor, tecnicos y gerente escriben en la conversacion y se etiqueta el equipo', function () {
    $gerente = usuarioConRol('Gerente', $this->team);
    $vendedor = usuarioConRol('Vendedor', $this->team, ['sede_id' => $this->sede->id]);
    $campo = usuarioConRol('TecnicoCampo', $this->team, ['sede_id' => $this->sede->id]);

    foreach ([[$gerente, 'gerente'], [$vendedor, 'vendedor'], [$campo, 'campo']] as [$usuario, $origen]) {
        $this->actingAs($usuario)
            ->post(($this->url)('ordenes.mensajes.store'), ['mensaje' => "Hola desde {$origen}", 'equipment_id' => $this->equipo->id])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('service_order_events', ['service_order_id' => $this->orden->id, 'user_id' => $usuario->id, 'equipment_id' => $this->equipo->id]);
    }

    expect($this->orden->events()->pluck('payload')->pluck('origen')->all())->toBe(['gerente', 'vendedor', 'campo']);
});

test('el mensaje con foto guarda una evidencia privada comprimida y se sirve con sesion', function () {
    $vendedor = usuarioConRol('Vendedor', $this->team, ['sede_id' => $this->sede->id]);

    $this->actingAs($vendedor)
        ->post(($this->url)('ordenes.mensajes.store'), ['archivo' => UploadedFile::fake()->image('foto.png', 3000, 2000)])
        ->assertSessionHasNoErrors();

    $evidencia = Evidencia::sole();
    [$ancho] = getimagesizefromstring(Storage::disk('local')->get($evidencia->path));

    expect($evidencia->tipo)->toBe('foto')
        ->and($evidencia->etapa)->toBe('conversacion')
        ->and($evidencia->mime)->toBe('image/jpeg')
        ->and($ancho)->toBeLessThanOrEqual(1600)
        ->and($evidencia->service_order_event_id)->not->toBeNull();

    $ruta = route('evidencias.show', ['current_team' => $this->team, 'evidencia' => $evidencia]);
    $this->actingAs($vendedor)->get($ruta)->assertOk();

    $otraSede = usuarioConRol('Vendedor', $this->team, ['sede_id' => Sede::factory()->create()->id]);
    $this->actingAs($otraSede)->get($ruta)->assertNotFound();
    auth()->logout();
    $this->get($ruta)->assertRedirect();
});

test('un audio y un archivo se guardan con su tipo y no se aceptan ejecutables', function () {
    $campo = usuarioConRol('TecnicoCampo', $this->team, ['sede_id' => $this->sede->id]);

    $this->actingAs($campo)->post(($this->url)('ordenes.mensajes.store'), ['archivo' => UploadedFile::fake()->create('nota.webm', 40, 'audio/webm')])->assertSessionHasNoErrors();
    $this->actingAs($campo)->post(($this->url)('ordenes.mensajes.store'), ['archivo' => UploadedFile::fake()->create('parte.pdf', 40, 'application/pdf')])->assertSessionHasNoErrors();
    $this->actingAs($campo)->post(($this->url)('ordenes.mensajes.store'), ['archivo' => UploadedFile::fake()->create('virus.php', 4, 'text/x-php')])->assertSessionHasErrors('archivo');

    expect(Evidencia::orderBy('id')->pluck('tipo')->all())->toBe(['audio', 'archivo']);
});

test('el mensaje necesita texto o adjunto y el equipo debe ser de la orden', function () {
    $vendedor = usuarioConRol('Vendedor', $this->team, ['sede_id' => $this->sede->id]);
    $ajeno = Equipment::factory()->create();

    $this->actingAs($vendedor)->post(($this->url)('ordenes.mensajes.store'), [])->assertSessionHasErrors('mensaje');
    $this->actingAs($vendedor)->post(($this->url)('ordenes.mensajes.store'), ['mensaje' => 'x', 'equipment_id' => $ajeno->id])->assertStatus(422);
});

test('en planta un item observado exige su foto y la deficiencia la guarda como evidencia', function () {
    $planta = usuarioConRol('TecnicoPlanta', $this->team, ['sede_id' => $this->sede->id]);
    $this->orden->update(['estado' => 'recibido_planta', 'departamento_tecnico' => 'planta']);
    $ruta = route('tecnico-planta.checklist.store', ['current_team' => $this->team, 'service_order' => $this->orden, 'equipment' => $this->equipo]);
    $items = fn (?UploadedFile $foto) => ['items' => ['manometro' => ['estado' => 'observado', 'condicion' => 'Roto', 'foto' => $foto]]];

    $this->actingAs($planta)->post($ruta, $items(null))->assertSessionHasErrors('items.manometro.foto');
    expect(Deficiency::count())->toBe(0);

    $this->actingAs($planta)->post($ruta, $items(UploadedFile::fake()->image('m.jpg')))->assertSessionHasNoErrors();

    $deficiencia = Deficiency::sole();
    expect($deficiencia->foto_path)->not->toBeNull()
        ->and(Evidencia::where('deficiency_id', $deficiencia->id)->where('etapa', 'deficiencia')->exists())->toBeTrue();
});

test('T5 la deficiencia fuera del checklist exige foto', function () {
    $planta = usuarioConRol('TecnicoPlanta', $this->team, ['sede_id' => $this->sede->id]);
    $this->orden->update(['estado' => 'recibido_planta', 'departamento_tecnico' => 'planta']);
    $ruta = route('tecnico-planta.deficiencias.store', ['current_team' => $this->team, 'service_order' => $this->orden]);
    $datos = ['componente' => 'Manguera', 'condicion' => 'Rota', 'equipment_id' => $this->equipo->id];

    $this->actingAs($planta)->post($ruta, $datos)->assertSessionHasErrors('foto');
    $this->actingAs($planta)->post($ruta, [...$datos, 'foto' => UploadedFile::fake()->image('d.jpg')])->assertSessionHasNoErrors();

    expect(Deficiency::sole()->foto_path)->not->toBeNull();
});

test('el tecnico de planta ya puede responder en la conversacion de la orden', function () {
    $planta = usuarioConRol('TecnicoPlanta', $this->team, ['sede_id' => $this->sede->id]);
    $this->orden->update(['estado' => 'recibido_planta', 'departamento_tecnico' => 'planta']);

    $this->actingAs($planta)->post(($this->url)('ordenes.mensajes.store'), ['mensaje' => 'Recibido, reviso hoy'])->assertSessionHasNoErrors();

    $this->actingAs($planta)->get(($this->url)('tecnico-planta.ejecucion.show'))
        ->assertInertia(fn ($page) => $page->has('conversacion.eventos', 1));
});

test('la entrega guarda la firma tactil y la rechaza si no es un PNG valido', function () {
    $campo = usuarioConRol('TecnicoCampo', $this->team, ['sede_id' => $this->sede->id]);
    $this->orden->update(['estado' => 'listo_entrega']);
    $datos = ['receptor_nombre' => 'Rosa Díaz', 'conformidad_aceptada' => true];

    $this->actingAs($campo)->post(($this->url)('tecnico-campo.entregas.confirm'), [...$datos, 'firma' => 'data:text/html;base64,AAAA'])->assertSessionHasErrors('firma');
    expect(Evidencia::count())->toBe(0);

    $this->actingAs($campo)->post(($this->url)('tecnico-campo.entregas.confirm'), [...$datos, 'firma' => FIRMA_PNG])->assertSessionHasNoErrors();

    $firma = Evidencia::sole();
    expect($firma->tipo)->toBe('firma')->and($firma->etapa)->toBe('entrega');
    Storage::disk('local')->assertExists($firma->path);
    $this->actingAs($campo)->get(($this->url)('tecnico-campo.entregas.pdf'))->assertOk();
});

test('T2 el mantenimiento en sitio exige checklist, fotos antes y despues y firma, y genera el acta', function () {
    $campo = usuarioConRol('TecnicoCampo', $this->team, ['sede_id' => $this->sede->id]);
    $this->orden->update(['service_id' => Service::factory()->create(['nombre' => 'Mantenimiento en sitio'])->id]);
    $cierre = ['conformidad_nombre' => 'Jefe de local', 'conformidad_aceptada' => true, 'firma' => FIRMA_PNG];

    $this->actingAs($campo)->get(route('tecnico-campo.mantenimientos.index', ['current_team' => $this->team]))
        ->assertOk()->assertInertia(fn ($page) => $page->component('tecnico-campo/mantenimientos/index')->has('mantenimientos.data', 1));
    $this->actingAs($campo)->get(($this->url)('tecnico-campo.mantenimientos.show'))
        ->assertOk()->assertInertia(fn ($page) => $page->component('tecnico-campo/mantenimientos/show'));

    $this->actingAs($campo)->post(($this->url)('tecnico-campo.mantenimientos.complete'), [...$cierre, 'firma' => null])->assertSessionHasErrors('firma');
    $this->actingAs($campo)->post(($this->url)('tecnico-campo.mantenimientos.complete'), $cierre)->assertSessionHasErrors('checklist');

    $this->actingAs($campo)->post(($this->url)('tecnico-campo.mantenimientos.checklist.store', ['equipment' => $this->equipo]), ['items' => ['manometro' => ['estado' => 'conforme']]])->assertSessionHasNoErrors();
    $this->actingAs($campo)->post(($this->url)('tecnico-campo.mantenimientos.complete'), $cierre)->assertSessionHasErrors('fotos');

    foreach (['antes', 'despues'] as $etapa) {
        $this->actingAs($campo)->post(($this->url)('ordenes.evidencias.store'), ['archivo' => UploadedFile::fake()->image("{$etapa}.jpg"), 'etapa' => $etapa, 'equipment_id' => $this->equipo->id])->assertSessionHasNoErrors();
    }

    $this->actingAs($campo)->post(($this->url)('tecnico-campo.mantenimientos.complete'), $cierre)->assertSessionHasNoErrors();

    expect($this->orden->fresh()->estado)->toBe('listo_entrega')
        ->and(Evidencia::where('etapa', 'mantenimiento')->where('tipo', 'firma')->exists())->toBeTrue();
    $this->actingAs($campo)->get(($this->url)('tecnico-campo.mantenimientos.pdf'))->assertOk();

    // Cerrar dos veces no se repite.
    $this->actingAs($campo)->post(($this->url)('tecnico-campo.mantenimientos.complete'), $cierre)->assertSessionHasErrors('estado');
});

test('T4 la instalacion exige escanear cada unidad vendida', function () {
    $campo = usuarioConRol('TecnicoCampo', $this->team, ['sede_id' => $this->sede->id]);
    $venta = Sale::factory()->create(['client_id' => $this->orden->client_id, 'sede_id' => $this->sede->id]);
    $vendida = Equipment::factory()->create(['client_id' => $venta->client_id, 'numero_serie' => 'BF-EQ-777001']);
    $otra = Equipment::factory()->create(['client_id' => $venta->client_id, 'numero_serie' => 'BF-EQ-777002']);
    foreach ([$vendida, $otra] as $unidad) {
        SaleItem::factory()->create(['sale_id' => $venta->id, 'equipment_id' => $unidad->id]);
    }
    $this->orden->update(['sale_id' => $venta->id, 'service_id' => Service::factory()->create(['nombre' => 'Instalación'])->id]);

    $datos = fn (array $ids) => [
        'area' => 'Almacén', 'ubicacion_instalada' => 'Pared norte', 'conformidad_nombre' => 'Jefe', 'conformidad_aceptada' => true,
        'emitir_certificado' => false, 'firma' => FIRMA_PNG,
        'equipos' => array_map(fn (int $id) => ['equipment_id' => $id], $ids),
    ];

    $this->actingAs($campo)->get(($this->url)('tecnico-campo.instalaciones.show'))
        ->assertInertia(fn ($page) => $page->has('unidadesVendidas', 2));

    $this->actingAs($campo)->post(($this->url)('tecnico-campo.instalaciones.store'), $datos([$vendida->id]))
        ->assertSessionHasErrors('equipos');
    expect($this->orden->fresh()->estado)->toBe('en_revision');

    $this->actingAs($campo)->post(($this->url)('tecnico-campo.instalaciones.store'), $datos([$vendida->id, $otra->id]))
        ->assertSessionHasNoErrors();
    expect($this->orden->fresh()->estado)->toBe('listo_entrega')
        ->and(Evidencia::where('etapa', 'instalacion')->where('tipo', 'firma')->exists())->toBeTrue();
});
