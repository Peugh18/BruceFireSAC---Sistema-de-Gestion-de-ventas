<?php

use App\Models\Certificate;
use App\Models\CertificateType;
use App\Models\Evidencia;
use App\Models\Service;
use App\Models\ServiceOrder;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

/** @return array{User, ServiceOrder, CertificateType, array<string, mixed>} */
function visitaConMedicion(string $atencion = 'Instalación de pozo a tierra'): array
{
    $user = User::factory()->create();
    $user->assignRole('TecnicoCampo');
    $tipo = CertificateType::factory()->create([
        'codigo' => 'pozo_a_tierra', 'nombre' => 'Pozo a tierra', 'version' => 1,
        'columnas' => [
            ['clave' => 'item', 'titulo' => 'Ítem', 'tipo' => 'texto'],
            ['clave' => 'resistencia', 'titulo' => 'Resistencia', 'tipo' => 'numero', 'unidad' => 'Ω', 'obligatorio' => true],
        ],
        'checklist' => [],
    ]);
    $service = Service::factory()->create(['nombre' => $atencion, 'certificate_type_id' => $tipo->id]);
    $orden = ServiceOrder::factory()->create(['service_id' => $service->id, 'departamento_tecnico' => 'campo', 'tecnico_id' => $user->id, 'estado' => 'en_proceso', 'sale_id' => null]);
    foreach (['antes', 'despues'] as $etapa) {
        Evidencia::factory()->create(['service_order_id' => $orden->id, 'etapa' => $etapa, 'tipo' => 'foto']);
    }
    $datos = [
        'area' => 'Patio del cliente', 'ubicacion_instalada' => 'Zona exterior',
        'conformidad_nombre' => 'Responsable del cliente', 'conformidad_aceptada' => true, 'firma' => FIRMA_PNG,
        'certificados_servicio' => [['tipo_id' => $tipo->id, 'version' => 1, 'filas' => [['resistencia' => '12,5']], 'pruebas' => []]],
    ];

    return [$user, $orden, $tipo, $datos];
}

test('campo recibe las columnas del certificado asociado al servicio de la orden', function () {
    [$user, $orden, $tipo] = visitaConMedicion();
    $this->actingAs($user)->get(route('tecnico-campo.instalaciones.show', ['current_team' => $user->currentTeam, 'service_order' => $orden]))
        ->assertOk()->assertInertia(fn (Assert $page) => $page->has('formulariosCertificado', 1)
        ->where('formulariosCertificado.0.id', $tipo->id)->where('formulariosCertificado.0.version', 1)
        ->where('formulariosCertificado.0.columnas.1.unidad', 'Ω'));
});

test('pozo a tierra aparece en instalaciones aunque el servicio este en una linea de la orden', function (bool $conPrincipal) {
    [$user, $orden] = visitaConMedicion('Pozo a tierra');
    $servicioId = $orden->service_id;
    $orden->update(['service_id' => $conPrincipal ? Service::factory()->create(['nombre' => 'Otro servicio'])->id : null, 'fecha' => today()]);
    $orden->lines()->create(['service_id' => $servicioId, 'cantidad' => 1]);
    $this->actingAs($user)->get(route('tecnico-campo.instalaciones.index', $user->currentTeam))
        ->assertOk()->assertInertia(fn (Assert $page) => $page->has('instalaciones.data', 1));
    $this->get(route('tecnico-campo.dashboard', $user->currentTeam))
        ->assertOk()->assertInertia(fn (Assert $page) => $page->where('orders.data.0.accion_sugerida', 'instalacion'));
})->with(['sin servicio principal' => false, 'con otro servicio principal' => true]);

test('finalizar campo emite el certificado desde la medicion sin extintores ni venta y sin duplicar', function (string $ruta, string $servicio) {
    [$user, $orden, $tipo, $datos] = visitaConMedicion($servicio);
    $url = route($ruta, ['current_team' => $user->currentTeam, 'service_order' => $orden]);

    $this->actingAs($user)->post($url, $datos)->assertSessionHasNoErrors()->assertRedirect();

    $certificado = Certificate::sole();
    expect($certificado->service_order_id)->toBe($orden->id);
    expect($certificado->sale_id)->toBeNull();
    expect($certificado->datos['filas'][0]['resistencia'])->toBe('12.5');
    expect($certificado->typeVersion->configuracion['columnas'][1]['unidad'])->toBe('Ω');
    expect($orden->fresh()->estado)->toBe('listo_entrega');
    $this->post($url, $datos)->assertSessionHasErrors();
    expect(Certificate::count())->toBe(1);
})->with([
    'instalacion' => ['tecnico-campo.instalaciones.store', 'Instalación de pozo a tierra'],
    'mantenimiento' => ['tecnico-campo.mantenimientos.complete', 'Mantenimiento de pozo a tierra'],
]);

test('la medicion obligatoria impide cerrar y emitir un certificado incompleto', function (string $valor) {
    [$user, $orden, $tipo, $datos] = visitaConMedicion();
    $datos['certificados_servicio'][0]['filas'][0]['resistencia'] = $valor;
    $this->actingAs($user)->post(route('tecnico-campo.instalaciones.store', ['current_team' => $user->currentTeam, 'service_order' => $orden]), $datos)
        ->assertSessionHasErrors('certificados_servicio.0.filas.0.resistencia');
    expect(Certificate::count())->toBe(0);
    expect($orden->fresh()->estado)->toBe('en_proceso');
})->with(['falta dato' => '', 'no numerico' => 'sin medir']);

test('campo exige recargar la plantilla si cambio despues de tomar las mediciones', function () {
    [$user, $orden, $tipo, $datos] = visitaConMedicion();
    $tipo->update(['version' => 2]);
    $this->actingAs($user)->post(route('tecnico-campo.instalaciones.store', ['current_team' => $user->currentTeam, 'service_order' => $orden]), $datos)
        ->assertSessionHasErrors('certificados_servicio.0.version');
    expect(Certificate::count())->toBe(0);
});

test('campo no certifica un tipo ajeno al servicio aunque envie sus mediciones', function () {
    [$user, $orden, $tipo, $datos] = visitaConMedicion();
    $datos['certificados_servicio'][0]['tipo_id'] = CertificateType::factory()->create()->id;
    $this->actingAs($user)->post(route('tecnico-campo.instalaciones.store', ['current_team' => $user->currentTeam, 'service_order' => $orden]), $datos)
        ->assertSessionHasErrors('certificados_servicio');
    expect(Certificate::count())->toBe(0);
});
