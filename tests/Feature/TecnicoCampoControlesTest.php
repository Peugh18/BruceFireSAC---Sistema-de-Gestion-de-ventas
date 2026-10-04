<?php

use App\Enums\TeamRole;
use App\Models\Certificate;
use App\Models\Client;
use App\Models\Equipment;
use App\Models\ServiceOrder;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\CertificateTypeSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed([RolesAndPermissionsSeeder::class, CertificateTypeSeeder::class]);
    $this->team = Team::factory()->create();
    $this->tecnico = User::factory()->create(['current_team_id' => $this->team->id]);
    $this->team->members()->attach($this->tecnico, ['role' => TeamRole::Admin->value]);
    $this->tecnico->assignRole('TecnicoCampo');
    $this->orden = ServiceOrder::factory()->create(['departamento_tecnico' => 'campo', 'estado' => 'en_revision']);
    $this->ruta = fn (string $nombre, array $extra = []) => route("tecnico-campo.{$nombre}", ['current_team' => $this->team, 'service_order' => $this->orden, ...$extra]);
});

test('no se agrega a la orden un extintor de otro cliente, ni por id ni por su serie', function () {
    $ajeno = Equipment::factory()->create(['client_id' => Client::factory()->create()->id, 'numero_serie' => 'BF-EQ-AJENO']);

    $this->actingAs($this->tecnico)->post(($this->ruta)('inspecciones.equipos.store'), ['equipment_id' => $ajeno->id])->assertSessionHasErrors('equipment_id');
    $this->actingAs($this->tecnico)->post(($this->ruta)('inspecciones.equipos.store'), ['numero_serie' => 'BF-EQ-AJENO'])->assertSessionHasErrors('equipment_id');

    expect($this->orden->equipments()->count())->toBe(0);
});

test('una serie ya registrada del cliente se reutiliza y uno nuevo no lleva fechas inventadas', function () {
    $propio = Equipment::factory()->create(['client_id' => $this->orden->client_id, 'numero_serie' => 'BF-EQ-PROPIO']);

    $this->actingAs($this->tecnico)->post(($this->ruta)('inspecciones.equipos.store'), ['numero_serie' => 'BF-EQ-PROPIO'])->assertSessionHasNoErrors();
    $this->actingAs($this->tecnico)->post(($this->ruta)('inspecciones.equipos.store'), ['numero_serie' => 'SERIE-NUEVA-1'])->assertSessionHasNoErrors();

    $nuevo = Equipment::where('numero_serie', 'SERIE-NUEVA-1')->sole();
    expect($this->orden->equipments()->pluck('equipment.id')->sort()->values()->all())->toBe(collect([$propio->id, $nuevo->id])->sort()->values()->all())
        ->and($nuevo->proxima_fecha_atencion)->toBeNull()
        ->and($nuevo->capacidad)->toBe('No legible');
});

test('el certificado de la inspeccion lleva solo los conformes y finalizar dos veces no lo duplica', function () {
    $conforme = Equipment::factory()->create(['client_id' => $this->orden->client_id, 'estado' => 'operativo']);
    $observado = Equipment::factory()->create(['client_id' => $this->orden->client_id, 'estado' => 'operativo']);
    $sinRevisar = Equipment::factory()->create(['client_id' => $this->orden->client_id, 'estado' => 'operativo']);
    $this->orden->equipments()->attach([$conforme->id, $observado->id, $sinRevisar->id]);

    $checklist = fn (Equipment $eq, string $estado) => $this->actingAs($this->tecnico)
        ->post(($this->ruta)('inspecciones.checklist.store', ['equipment' => $eq]), ['items' => ['manometro' => ['estado' => $estado, 'condicion' => 'Revisado']]]);
    $checklist($conforme, 'conforme')->assertSessionHasNoErrors();
    $checklist($observado, 'observado')->assertSessionHasNoErrors();

    $finalizar = fn () => $this->actingAs($this->tecnico)->post(($this->ruta)('inspecciones.complete'), ['conformidad_nombre' => 'Jefe de seguridad', 'conformidad_aceptada' => true]);
    $finalizar()->assertSessionHasNoErrors()->assertRedirect();

    $certificado = Certificate::where('service_order_id', $this->orden->id)->sole();
    expect($certificado->certificateUnits()->pluck('equipment_id')->all())->toBe([$conforme->id])
        ->and($certificado->tipo_atencion)->toBe('inspeccion');

    $finalizar()->assertSessionHasErrors('estado');
    expect(Certificate::where('service_order_id', $this->orden->id)->count())->toBe(1);
});

test('la entrega en campo es una sola y no reactiva un extintor descargado', function () {
    $descargado = Equipment::factory()->create(['client_id' => $this->orden->client_id, 'estado' => 'descargado', 'proxima_fecha_atencion' => today()->subDay()]);
    $this->orden->equipments()->attach($descargado->id);
    $this->orden->update(['estado' => 'listo_entrega']);

    $entregar = fn () => $this->actingAs($this->tecnico)->post(($this->ruta)('entregas.confirm'), ['receptor_nombre' => 'Cliente', 'conformidad_aceptada' => true]);
    $entregar()->assertRedirect();

    expect($descargado->fresh()->estado)->toBe('descargado');

    $this->orden->update(['estado' => 'listo_entrega']);
    $entregar()->assertStatus(422);
});
