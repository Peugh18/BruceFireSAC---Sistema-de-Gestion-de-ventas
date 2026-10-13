<?php

use App\Actions\Certificates\IssueCertificate;
use App\Enums\TeamRole;
use App\Models\Certificate;
use App\Models\CertificateType;
use App\Models\CertificateUnit;
use App\Models\Equipment;
use App\Models\ServiceOrder;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\CertificateTypeSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    Storage::fake('public');
    $this->seed([RolesAndPermissionsSeeder::class, CertificateTypeSeeder::class]);
    $this->team = Team::factory()->create();
    $this->tecnico = User::factory()->create(['current_team_id' => $this->team->id]);
    $this->team->members()->attach($this->tecnico, ['role' => TeamRole::Admin->value]);
    $this->tecnico->assignRole('TecnicoPlanta');
    $this->orden = ServiceOrder::factory()->create(['estado' => 'recibido_planta', 'sede_id' => $this->tecnico->sede_id]);
    $this->equipo = Equipment::factory()->create(['client_id' => $this->orden->client_id, 'estado' => 'activo']);
    $this->orden->equipments()->attach($this->equipo->id, ['recibido' => true]);
    $this->certificados = collect(['prueba_hidrostatica', 'operatividad_garantia'])->map(function (string $codigo) {
        $certificado = Certificate::factory()->create(['certificate_type_id' => CertificateType::where('codigo', $codigo)->sole()->id, 'client_id' => $this->equipo->client_id, 'estado' => 'vigente']);
        CertificateUnit::factory()->create(['certificate_id' => $certificado->id, 'equipment_id' => $this->equipo->id]);

        return $certificado;
    });
    $this->ruta = route('tecnico-planta.checklist.store', ['current_team' => $this->team, 'service_order' => $this->orden, 'equipment' => $this->equipo]);
    $this->datos = ['equipo_no_apto' => true, 'observaciones' => 'Cilindro picado: no apto para uso.', 'items' => ['corrosion' => ['estado' => 'observado', 'condicion' => 'Cilindro picado', 'foto' => UploadedFile::fake()->image('cilindro.jpg')]]];
});

test('declarar no apto un extintor anula sus certificados tecnicos y conserva el historial', function () {
    $ajeno = Certificate::factory()->create(['certificate_type_id' => CertificateType::where('codigo', 'operatividad_garantia')->sole()->id, 'client_id' => $this->equipo->client_id, 'estado' => 'vigente']);
    CertificateUnit::factory()->create(['certificate_id' => $ajeno->id]);
    $capacitacion = Certificate::factory()->create(['certificate_type_id' => CertificateType::where('codigo', 'capacitacion')->sole()->id, 'client_id' => $this->equipo->client_id, 'estado' => 'vigente']);
    $this->actingAs($this->tecnico)->post($this->ruta, $this->datos)->assertSessionHasNoErrors();
    expect($this->equipo->fresh()->estado)->toBe('baja')
        ->and($this->orden->equiposAptos())->toHaveCount(0);
    foreach ($this->certificados as $certificado) {
        expect($certificado->fresh()->estado)->toBe('anulado')
            ->and($certificado->fresh()->anulado_por)->toBe($this->tecnico->id)
            ->and($certificado->fresh()->anulado_motivo)->toContain('Cilindro picado')
            ->and($certificado->certificateUnits()->count())->toBe(1);
    }
    expect($ajeno->fresh()->estado)->toBe('vigente')
        ->and($capacitacion->fresh()->estado)->toBe('vigente');
});

test('una observacion reparable no anula la prueba hidrostática', function () {
    $this->datos['equipo_no_apto'] = false;
    $this->actingAs($this->tecnico)->post($this->ruta, $this->datos)->assertSessionHasNoErrors();
    expect($this->equipo->fresh()->estado)->toBe('activo')
        ->and(Certificate::whereIn('id', $this->certificados->pluck('id')->all())->pluck('estado')->all())->toBe(['vigente', 'vigente']);
});

test('declarar no apto exige un motivo y no cambia certificados si falta', function () {
    $this->datos['observaciones'] = '';
    $this->actingAs($this->tecnico)->post($this->ruta, $this->datos)->assertSessionHasErrors('observaciones');
    expect($this->equipo->fresh()->estado)->toBe('activo')
        ->and(Certificate::whereIn('id', $this->certificados->pluck('id')->all())->pluck('estado')->all())->toBe(['vigente', 'vigente']);
});

test('no se puede dar de baja desde un checklist un equipo que pertenece a otra orden', function () {
    $otraOrden = ServiceOrder::factory()->create(['sede_id' => $this->tecnico->sede_id, 'estado' => 'recibido_planta']);
    $ruta = route('tecnico-planta.checklist.store', ['current_team' => $this->team, 'service_order' => $otraOrden, 'equipment' => $this->equipo]);
    $this->actingAs($this->tecnico)->post($ruta, $this->datos)->assertSessionHasErrors('equipment');
    expect($this->equipo->fresh()->estado)->toBe('activo');
});

test('un equipo declarado no apto no puede recibir un nuevo certificado operativo', function () {
    $this->actingAs($this->tecnico)->post($this->ruta, $this->datos)->assertSessionHasNoErrors();
    app(IssueCertificate::class)->handle(
        CertificateType::where('codigo', 'operatividad_garantia')->sole(),
        $this->equipo->client,
        [['equipment_id' => $this->equipo->id]],
    );
})->throws(ValidationException::class);

test('campo declara no apto y anula los certificados del mismo extintor', function (string $area) {
    $this->tecnico->syncRoles('TecnicoCampo');
    $this->orden->update(['departamento_tecnico' => 'campo', 'tecnico_id' => $this->tecnico->id, 'estado' => 'en_proceso']);
    $ruta = route("tecnico-campo.{$area}.checklist.store", ['current_team' => $this->team, 'service_order' => $this->orden, 'equipment' => $this->equipo]);
    $this->actingAs($this->tecnico)->post($ruta, $this->datos)->assertSessionHasNoErrors();
    expect($this->equipo->fresh()->estado)->toBe('baja')
        ->and(Certificate::whereIn('id', $this->certificados->pluck('id')->all())->pluck('estado')->all())->toBe(['anulado', 'anulado']);
})->with(['inspecciones', 'mantenimientos']);
