<?php

use App\Enums\TeamRole;
use App\Models\Certificate;
use App\Models\Deficiency;
use App\Models\Equipment;
use App\Models\Sale;
use App\Models\ServiceOrder;
use App\Models\Team;
use App\Models\TechnicalChecklist;
use App\Models\User;
use Database\Seeders\CertificateTypeSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed([RolesAndPermissionsSeeder::class, CertificateTypeSeeder::class]);
    $this->team = Team::factory()->create();
    $this->tecnico = User::factory()->create(['current_team_id' => $this->team->id]);
    $this->team->members()->attach($this->tecnico, ['role' => TeamRole::Admin->value]);
    $this->tecnico->assignRole('TecnicoPlanta');
    $this->orden = ServiceOrder::factory()->create(['estado' => 'pendiente_recepcion', 'departamento_tecnico' => 'planta', 'sede_id' => $this->tecnico->sede_id]);
    $this->equipos = Equipment::factory()->count(3)->create(['client_id' => $this->orden->client_id, 'proxima_fecha_atencion' => today()->subMonth(), 'proxima_prueba_hidrostatica' => today()->subMonth()]);
    $this->orden->equipments()->attach($this->equipos->modelKeys());
    $this->ruta = fn (string $nombre, array $extra = []) => route("tecnico-planta.{$nombre}", ['current_team' => $this->team, 'service_order' => $this->orden, ...$extra]);
});

function checklistConforme(Equipment $equipo): void
{
    TechnicalChecklist::create(['service_order_id' => test()->orden->id, 'equipment_id' => $equipo->id, 'user_id' => test()->tecnico->id, 'origen' => 'planta', 'items' => [], 'resultado_general' => 'conforme']);
}

test('se certifica solo lo que llego y no se rechazo, y la ph va por extintor', function () {
    [$llego, $rechazado, $noLlego] = $this->equipos;

    $this->actingAs($this->tecnico)->post(($this->ruta)('recepciones.confirm'), ['equipos_recibidos' => [$llego->id, $rechazado->id]])->assertSessionHasNoErrors();

    // No se puede terminar el trabajo sin el checklist de cada extintor recibido.
    $this->orden->update(['estado' => 'en_proceso']);
    $avanzar = fn (string $estado, array $extra = []) => $this->actingAs($this->tecnico)->post(($this->ruta)('ejecucion.advance'), ['target_state' => $estado, ...$extra]);
    $avanzar('trabajo_terminado')->assertSessionHasErrors('target_state');

    checklistConforme($llego);
    checklistConforme($rechazado);
    Deficiency::create(['service_order_id' => $this->orden->id, 'equipment_id' => $rechazado->id, 'componente' => 'Válvula', 'condicion' => 'Fuga', 'estado' => 'rechazada']);

    $avanzar('trabajo_terminado')->assertSessionHasNoErrors();
    $avanzar('listo_certificado', ['ph_equipos' => [$llego->id]])->assertSessionHasNoErrors();

    $operatividad = Certificate::where('service_order_id', $this->orden->id)->whereHas('certificateType', fn ($q) => $q->where('codigo', '!=', 'prueba_hidrostatica'))->sole();
    expect($operatividad->certificateUnits()->pluck('equipment_id')->all())->toBe([$llego->id])
        ->and($llego->fresh()->proxima_fecha_atencion->isFuture())->toBeTrue()
        ->and($llego->fresh()->proxima_prueba_hidrostatica->toDateString())->toBe(now()->addYears(5)->toDateString())
        ->and($rechazado->fresh()->proxima_fecha_atencion->isPast())->toBeTrue()
        ->and($noLlego->fresh()->proxima_fecha_atencion->isPast())->toBeTrue();
});

test('con el certificado emitido no se agregan deficiencias y sin recibir no hay checklist', function () {
    $this->actingAs($this->tecnico)->post(($this->ruta)('checklist.store', ['equipment' => $this->equipos[0]]), ['items' => ['manometro' => ['estado' => 'conforme']]])
        ->assertSessionHasErrors('equipment');

    $this->orden->update(['estado' => 'listo_certificado']);
    $this->actingAs($this->tecnico)->post(($this->ruta)('deficiencias.store'), ['componente' => 'Manguera', 'condicion' => 'Rota'])
        ->assertSessionHasErrors('componente');

    expect(Deficiency::count())->toBe(0)
        ->and($this->orden->fresh()->estado)->toBe('listo_certificado');
});

test('el vendedor anula una orden antes del certificado y no si ya se cobro o se certifico', function () {
    $vendedor = User::factory()->create(['sede_id' => $this->orden->sede_id]);
    $vendedor->assignRole('Vendedor');
    $anular = fn (ServiceOrder $orden) => $this->actingAs($vendedor)->post(route('vendedor.ordenes-servicio.anular', ['current_team' => $vendedor->currentTeam, 'service_order' => $orden]), ['motivo' => 'El cliente se arrepintió']);

    $anular($this->orden)->assertSessionHasNoErrors();
    expect($this->orden->fresh()->estado)->toBe('anulada');

    $certificada = ServiceOrder::factory()->create(['estado' => 'listo_certificado', 'sede_id' => $this->orden->sede_id]);
    $anular($certificada)->assertSessionHasErrors('motivo');

    $cobrada = ServiceOrder::factory()->create(['estado' => 'en_proceso', 'sede_id' => $this->orden->sede_id, 'sale_id' => Sale::factory()->create(['estado' => 'confirmada'])->id]);
    $anular($cobrada)->assertSessionHasErrors('motivo');

    expect($certificada->fresh()->estado)->toBe('listo_certificado')
        ->and($cobrada->fresh()->estado)->toBe('en_proceso');

    // Una orden anulada no se recibe en el taller.
    $this->actingAs($this->tecnico)->post(($this->ruta)('recepciones.confirm'))->assertSessionHasErrors();
});
