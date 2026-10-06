<?php

use App\Contracts\SunatClientInterface;
use App\Models\DocumentSeries;
use App\Models\ElectronicDocument;
use App\Models\NoteRequest;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Fixtures\SunatSoloEnvio;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Storage::fake('local');
    config(['billing.sunat.cert_path' => base_path('tests/Fixtures/certificates/test-certificate.pem')]);

    $this->sunat = new class extends SunatSoloEnvio
    {
        public int $enviados = 0;

        public function send(string $xmlSigned, string $documentName): array
        {
            $this->enviados++;

            return ['cdr_zip' => 'cdr', 'codigo' => 0, 'mensaje' => 'Aceptada', 'notas' => []];
        }
    };
    $this->app->instance(SunatClientInterface::class, $this->sunat);

    [$this->sale, $this->factura] = ventaConFactura();
    $this->vendedor = User::findOrFail($this->sale->vendedor_id);
    $this->gerente = User::factory()->create();
    $this->gerente->assignRole('Gerente');
});

function pedirNota(User $vendedor, ElectronicDocument $factura, string $tipo = 'credito', array $datos = []): TestResponse
{
    return test()->actingAs($vendedor)->post(route("vendedor.notas-{$tipo}.store", ['current_team' => $vendedor->currentTeam]), [
        'electronic_document_id' => $factura->id,
        'motivo_catalogo' => $tipo === 'credito' ? '07' : '01',
        'detalle' => 'Devolvió un equipo',
        'importe' => 20,
        ...$datos,
    ]);
}

test('un vendedor sin permiso no puede pedir notas de crédito ni de débito', function () {
    $this->vendedor->roles->first()->revokePermissionTo('billing.credit_note');

    pedirNota($this->vendedor, $this->factura)->assertForbidden();
    pedirNota($this->vendedor, $this->factura, 'debito')->assertForbidden();

    expect(NoteRequest::count())->toBe(0);
});

test('la nota que pide un vendedor queda por aprobar sin número ni envío a SUNAT', function () {
    pedirNota($this->vendedor, $this->factura)->assertSessionHasNoErrors()->assertSessionHas('success');

    $solicitud = NoteRequest::sole();

    expect($solicitud->estado)->toBe('por_aprobar')
        ->and($solicitud->tipo)->toBe('nota_credito')
        ->and($solicitud->motivo_catalogo)->toBe('07')
        ->and((float) $solicitud->importe)->toBe(20.0)
        ->and($solicitud->solicitado_por)->toBe($this->vendedor->id)
        ->and(ElectronicDocument::where('tipo', 'nota_credito')->exists())->toBeFalse()
        ->and(DocumentSeries::where('serie', 'FC01')->exists())->toBeFalse()
        ->and($this->sunat->enviados)->toBe(0);
});

test('si quien la pide también es gerente la nota sale directo', function () {
    $this->vendedor->assignRole('Gerente');

    pedirNota($this->vendedor, $this->factura)->assertSessionHasNoErrors();

    expect(NoteRequest::count())->toBe(0)
        ->and(ElectronicDocument::where('tipo', 'nota_credito')->value('sunat_estado'))->toBe('aceptado');
});

test('la solicitud valida las reglas de SUNAT antes de llegar al gerente', function () {
    [, $boleta] = ventaConFactura(tipo: 'boleta');
    $boleta->sale->update(['vendedor_id' => $this->vendedor->id]);

    pedirNota($this->vendedor, $boleta, datos: ['motivo_catalogo' => '04'])->assertSessionHasErrors('motivo_catalogo');

    expect(NoteRequest::count())->toBe(0);
});

test('el gerente ve las notas por aprobar en su panel', function () {
    pedirNota($this->vendedor, $this->factura);

    $this->actingAs($this->gerente)
        ->get(route('gerente.notas.index', ['current_team' => $this->gerente->currentTeam]))
        ->assertInertia(fn (Assert $page) => $page
            ->component('gerente/notas/index')
            ->has('solicitudes', 1)
            ->where('solicitudes.0.solicitante', $this->vendedor->name));

    $this->actingAs($this->gerente)
        ->get(route('gerente.dashboard', ['current_team' => $this->gerente->currentTeam]))
        ->assertInertia(fn (Assert $page) => $page->where('metrics.notasPorAprobar', 1));
});

test('cuando el gerente aprueba se emite la nota con el flujo normal', function (string $tipo) {
    pedirNota($this->vendedor, $this->factura, $tipo);
    $solicitud = NoteRequest::sole();

    $this->actingAs($this->gerente)
        ->post(route('gerente.notas.aprobar', ['current_team' => $this->gerente->currentTeam, 'note_request' => $solicitud]))
        ->assertSessionHasNoErrors();

    $solicitud->refresh();
    $nota = ElectronicDocument::findOrFail($solicitud->nota_id);

    expect($solicitud->estado)->toBe('aprobada')
        ->and($solicitud->revisado_por)->toBe($this->gerente->id)
        ->and($nota->tipo)->toBe("nota_{$tipo}")
        ->and($nota->cpe_afectado_id)->toBe($this->factura->id)
        ->and((float) $nota->importe)->toBe(20.0)
        ->and($nota->sunat_estado)->toBe('aceptado')
        ->and($this->sunat->enviados)->toBe(1);
})->with(['credito', 'debito']);

test('el gerente rechaza la nota con un motivo y no se emite nada', function () {
    pedirNota($this->vendedor, $this->factura);
    $solicitud = NoteRequest::sole();

    $this->actingAs($this->gerente)
        ->post(route('gerente.notas.rechazar', ['current_team' => $this->gerente->currentTeam, 'note_request' => $solicitud]), [])
        ->assertSessionHasErrors('motivo_rechazo');

    $this->actingAs($this->gerente)
        ->post(route('gerente.notas.rechazar', ['current_team' => $this->gerente->currentTeam, 'note_request' => $solicitud]), [
            'motivo_rechazo' => 'El cliente no devolvió el equipo',
        ])
        ->assertSessionHasNoErrors();

    expect($solicitud->fresh()->estado)->toBe('rechazada')
        ->and($solicitud->fresh()->motivo_rechazo)->toBe('El cliente no devolvió el equipo')
        ->and(ElectronicDocument::where('tipo', 'nota_credito')->exists())->toBeFalse();
});

test('una solicitud ya resuelta no se aprueba dos veces', function () {
    pedirNota($this->vendedor, $this->factura);
    $solicitud = NoteRequest::sole();
    $ruta = route('gerente.notas.aprobar', ['current_team' => $this->gerente->currentTeam, 'note_request' => $solicitud]);

    $this->actingAs($this->gerente)->post($ruta);
    $this->actingAs($this->gerente)->post($ruta)->assertSessionHasErrors();

    expect(ElectronicDocument::where('tipo', 'nota_credito')->count())->toBe(1);
});

test('un vendedor no entra al panel de aprobación', function () {
    $this->actingAs($this->vendedor)
        ->get(route('gerente.notas.index', ['current_team' => $this->vendedor->currentTeam]))
        ->assertForbidden();
});
