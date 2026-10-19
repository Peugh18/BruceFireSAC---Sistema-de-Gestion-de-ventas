<?php

use App\Models\AuditLog;
use App\Models\Certificate;
use App\Models\CertificateType;
use App\Models\CertificateUnit;
use App\Models\ServiceOrder;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->vendedor = vendedorUser();
    $orden = ServiceOrder::factory()->create();
    $tipo = CertificateType::firstOrCreate(
        ['codigo' => 'operatividad_garantia'],
        ['nombre' => 'Certificado de Operatividad y Garantía', 'vigencia_meses' => 12, 'generado_por_rol' => 'tecnico_planta']
    );
    $this->certificado = Certificate::factory()->create([
        'certificate_type_id' => $tipo->id,
        'client_id' => $orden->client_id,
        'service_order_id' => $orden->id,
        'sale_id' => null,
        'estado' => 'vigente',
        'direccion' => 'Av. Equivocada 1',
        'revision' => 0,
    ]);
    $this->unidad = CertificateUnit::factory()->create([
        'certificate_id' => $this->certificado->id,
        'numero_serie_snapshot' => 'MAL-001',
        'marca' => 'Badger',
        'capacidad' => '6 kg',
        'tipo_agente' => 'PQS ABC',
    ]);
    $this->ruta = route('vendedor.certificados.corregir', ['current_team' => $this->vendedor->currentTeam, 'certificate' => $this->certificado]);
});

test('el vendedor corrige un certificado de planta y conserva el mismo número', function () {
    $numero = $this->certificado->numero;

    $this->actingAs($this->vendedor)->patch($this->ruta, [
        'direccion' => 'Av. España 1245, Trujillo',
        'motivo' => 'La serie y la dirección estaban mal',
        'unidades' => [['id' => $this->unidad->id, 'numero_serie' => 'AB-12345', 'marca' => 'Badger', 'capacidad' => '6 kg']],
    ])->assertSessionHasNoErrors();

    $certificado = $this->certificado->fresh()->load('certificateUnits');
    expect($certificado->numero)->toBe($numero)
        ->and($certificado->revision)->toBe(1)
        ->and($certificado->direccion)->toBe('Av. España 1245, Trujillo')
        ->and($certificado->certificateUnits->sole()->numero_serie_snapshot)->toBe('AB-12345')
        ->and(AuditLog::query()->where('action', 'certificado.corregido')->exists())->toBeTrue();
});

test('corregir exige el motivo y no toca un certificado anulado', function () {
    $this->actingAs($this->vendedor)
        ->patch($this->ruta, ['direccion' => 'Otra', 'motivo' => '', 'unidades' => []])
        ->assertSessionHasErrors('motivo');

    $this->certificado->update(['estado' => 'anulado']);
    $this->patch($this->ruta, ['direccion' => 'Otra', 'motivo' => 'Error', 'unidades' => []])
        ->assertSessionHasErrors('certificado');

    expect($this->certificado->fresh()->direccion)->toBe('Av. Equivocada 1');
});

test('quien no puede imprimir certificados no los corrige', function () {
    $tecnico = User::factory()->create(['current_team_id' => $this->vendedor->current_team_id]);
    $tecnico->assignRole('TecnicoPlanta');

    $this->actingAs($tecnico)
        ->patch($this->ruta, ['direccion' => 'Otra', 'motivo' => 'Error', 'unidades' => []])
        ->assertForbidden();
});
