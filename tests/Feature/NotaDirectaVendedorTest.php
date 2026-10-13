<?php

use App\Contracts\SunatClientInterface;
use App\Models\ElectronicDocument;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
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
    $this->team = ['current_team' => $this->vendedor->currentTeam];
});

test('la pantalla de la nota trae la misma factura con sus ítems y lo que queda por devolver', function () {
    $this->actingAs($this->vendedor)
        ->get(route('vendedor.ventas.nota', [...$this->team, 'sale' => $this->sale, 'tipo' => 'credito']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('vendedor/ventas/nota')
            ->where('original.id', $this->factura->id)
            ->where('porAcreditar', fn ($valor) => (float) $valor === (float) $this->sale->total)
            ->has('lineas', 1));
});

test('el vendedor emite la nota de crédito directo y sale a SUNAT sin esperar al Gerente', function () {
    $this->actingAs($this->vendedor)
        ->post(route('vendedor.notas-credito.store', $this->team), [
            'electronic_document_id' => $this->factura->id,
            'motivo_catalogo' => '01',
            'detalle' => 'Anulación de la operación',
            'importe' => (float) $this->sale->total,
        ])
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success');

    $nota = ElectronicDocument::where('tipo', 'nota_credito')->sole();
    expect($nota->sunat_estado)->toBe('aceptado')
        ->and($nota->cpe_afectado_id)->toBe($this->factura->id)
        ->and($this->sunat->enviados)->toBe(1);
});

test('el vendedor emite la nota de débito directo por un aumento de precio', function () {
    $this->actingAs($this->vendedor)
        ->post(route('vendedor.notas-debito.store', $this->team), [
            'electronic_document_id' => $this->factura->id,
            'motivo_catalogo' => '02',
            'detalle' => 'Aumento en el valor: extintor',
            'importe' => 20,
        ])
        ->assertSessionHasNoErrors();

    expect(ElectronicDocument::where('tipo', 'nota_debito')->sole())
        ->sunat_estado->toBe('aceptado')
        ->motivo_catalogo->toBe('02');
});

test('sin el permiso no se emiten notas ni se abre la pantalla', function () {
    $this->vendedor->roles->first()->revokePermissionTo('billing.credit_note');

    $this->actingAs($this->vendedor)
        ->get(route('vendedor.ventas.nota', [...$this->team, 'sale' => $this->sale, 'tipo' => 'credito']))
        ->assertForbidden();
    $this->post(route('vendedor.notas-credito.store', $this->team), [
        'electronic_document_id' => $this->factura->id, 'motivo_catalogo' => '01', 'detalle' => 'x', 'importe' => 10,
    ])->assertForbidden();

    expect(ElectronicDocument::whereIn('tipo', ['nota_credito', 'nota_debito'])->exists())->toBeFalse();
});

test('retirar la aprobacion conserva solicitudes pendientes y revisadas en el archivo', function () {
    $migracion = require database_path('migrations/2026_10_12_225648_drop_note_requests_table.php');
    $migracion->down();

    try {
        $ids = [];
        foreach (['por_aprobar', 'aprobada', 'rechazada'] as $estado) {
            $ids[] = DB::table('note_requests')->insertGetId([
                'electronic_document_id' => $this->factura->id,
                'tipo' => 'nota_credito', 'motivo_catalogo' => '01',
                'detalle' => 'Solicitud anterior', 'importe' => $this->sale->total,
                'estado' => $estado, 'solicitado_por' => $this->vendedor->id,
            ]);
        }

        $migracion->up();

        expect(Schema::hasTable('note_requests'))->toBeFalse()
            ->and(DB::table('note_requests_archive')->whereIn('id', $ids)->orderBy('id')->pluck('estado')->all())
            ->toBe(['por_aprobar', 'aprobada', 'rechazada']);
    } finally {
        if (Schema::hasTable('note_requests') && ! Schema::hasTable('note_requests_archive')) {
            Schema::rename('note_requests', 'note_requests_archive');
        }
    }
});
