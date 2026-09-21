<?php

use App\Actions\Certificates\IssueCertificate;
use App\Models\Certificate;
use App\Models\CertificateType;
use App\Models\Client;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('issue certificate creates a certificate with technical units, correct prefix and calculated vigencia', function () {
    $tipoOG = CertificateType::firstOrCreate(
        ['codigo' => 'operatividad_garantia'],
        [
            'nombre' => 'Operatividad y Garantía',
            'vigencia_meses' => 12,
            'generado_por_rol' => 'tecnico_planta',
        ]
    );

    $client = Client::factory()->create();

    $unidades = [
        [
            'numero_serie' => 'SN-12345',
            'fecha_ultima_ph' => null,
            'fecha_ultima_recarga' => '2026-03-10',
        ],
        [
            'numero_serie' => 'SN-67890',
            'fecha_ultima_ph' => null,
            'fecha_ultima_recarga' => '2026-03-10',
        ],
    ];

    $action = app(IssueCertificate::class);
    $certificate = $action->handle($tipoOG, $client, $unidades);

    expect($certificate)->toBeInstanceOf(Certificate::class)
        ->and($certificate->numero)->toStartWith('OG-'.now()->year.'-')
        ->and($certificate->fecha_emision->toDateString())->toBe(now()->toDateString())
        ->and($certificate->fecha_vigencia_hasta->toDateString())->toBe('2027-03-10')
        ->and($certificate->estado)->toBe('vigente')
        ->and($certificate->qr_token)->not->toBeEmpty()
        ->and($certificate->certificateUnits)->toHaveCount(2)
        ->and($certificate->certificateUnits->first()->numero_serie_snapshot)->toBe('SN-12345');

    $this->assertDatabaseHas('certificates', [
        'id' => $certificate->id,
        'numero' => $certificate->numero,
        'certificate_type_id' => $tipoOG->id,
        'client_id' => $client->id,
        'estado' => 'vigente',
    ]);

    $this->assertDatabaseHas('certificate_units', [
        'certificate_id' => $certificate->id,
        'numero_serie_snapshot' => 'SN-12345',
    ]);

    $this->assertDatabaseHas('certificate_units', [
        'certificate_id' => $certificate->id,
        'numero_serie_snapshot' => 'SN-67890',
    ]);
});

test('issue certificate for prueba hidrostatica calculates 5 years vigencia', function () {
    $tipoPH = CertificateType::firstOrCreate(
        ['codigo' => 'prueba_hidrostatica'],
        [
            'nombre' => 'Prueba Hidrostática',
            'vigencia_meses' => 60,
            'generado_por_rol' => 'tecnico_planta',
        ]
    );

    $client = Client::factory()->create();

    $unidades = [
        [
            'numero_serie' => 'SN-PH-99',
            'fecha_ultima_ph' => '2026-04-20',
            'fecha_ultima_recarga' => null,
        ],
    ];

    $certificate = app(IssueCertificate::class)->handle($tipoPH, $client, $unidades);

    expect($certificate->numero)->toStartWith('PH-'.now()->year.'-')
        ->and($certificate->fecha_vigencia_hasta->toDateString())->toBe('2031-04-20');
});

test('vendedor user can view index and show of certificates', function () {
    $user = vendedorUser();
    $client = Client::factory()->create();
    $tipo = CertificateType::factory()->create([
        'codigo' => 'operatividad_garantia',
        'nombre' => 'Operatividad y Garantía',
    ]);

    $certificate = Certificate::factory()->create([
        'certificate_type_id' => $tipo->id,
        'client_id' => $client->id,
    ]);

    $responseIndex = $this
        ->actingAs($user)
        ->get(route('vendedor.certificados.index', ['current_team' => $user->currentTeam]));

    $responseIndex->assertOk();

    $responseShow = $this
        ->actingAs($user)
        ->get(route('vendedor.certificados.show', [
            'current_team' => $user->currentTeam,
            'certificate' => $certificate,
        ]));

    $responseShow->assertOk();
});

test('public route verifies certificate with valid token and returns 404 with invalid token without auth', function () {
    $tipo = CertificateType::factory()->create([
        'codigo' => 'operatividad_garantia',
        'nombre' => 'Operatividad y Garantía',
    ]);
    $client = Client::factory()->create(['razon_social' => 'EMPRESA TEST SAC']);

    $certificate = Certificate::factory()->create([
        'certificate_type_id' => $tipo->id,
        'client_id' => $client->id,
        'qr_token' => (string) Str::uuid(),
    ]);

    $responseValid = $this->get(route('certificados.verificar', ['token' => $certificate->qr_token]));
    $responseValid->assertOk()
        ->assertJson([
            'numero' => $certificate->numero,
            'tipo' => 'Operatividad y Garantía',
            'cliente' => 'EMPRESA TEST SAC',
        ]);

    $responseInvalid = $this->get(route('certificados.verificar', ['token' => 'token-inexistente-12345']));
    $responseInvalid->assertNotFound();
});
