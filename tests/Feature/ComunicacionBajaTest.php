<?php

use App\Actions\Billing\VoidElectronicDocument;
use App\Contracts\SunatClientInterface;
use App\Models\CompanySetting;
use App\Models\ElectronicDocument;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Storage::fake('local');
    config(['billing.sunat.cert_path' => base_path('tests/Fixtures/certificates/test-certificate.pem')]);

    // El nombre del comprobante de baja lleva el RUC de la empresa. Sin este
    // registro la prueba depende del .env local y en el CI sale vacío.
    CompanySetting::factory()->create();

    // SUNAT simulado: recibe la baja, da un ticket y luego responde lo que
    // diga $estado (en proceso, aceptada o rechazada).
    $this->sunat = new class implements SunatClientInterface
    {
        /** @var list<array{nombre: string, xml: string}> */
        public array $resumenes = [];

        /** @var array{en_proceso: bool, cdr_zip: string|null, codigo: int, mensaje: string, notas: list<string>} */
        public array $estado = ['en_proceso' => true, 'cdr_zip' => null, 'codigo' => 98, 'mensaje' => 'En proceso', 'notas' => []];

        public function send(string $xmlSigned, string $documentName): array
        {
            throw new LogicException('La baja no reenvía el comprobante.');
        }

        public function sendSummary(string $xmlSigned, string $documentName): array
        {
            $this->resumenes[] = ['nombre' => $documentName, 'xml' => $xmlSigned];

            return ['ticket' => '1700000000'.count($this->resumenes), 'mensaje' => 'Recibido'];
        }

        public function getStatus(string $ticket): array
        {
            return $this->estado;
        }

        public function consultCdr(string $ruc, string $tipoDoc, string $serie, int $numero): array
        {
            throw new LogicException('La baja no consulta el CDR de un comprobante.');
        }
    };
    $this->app->instance(SunatClientInterface::class, $this->sunat);
});

function sunatAceptaLaBaja(object $sunat): void
{
    $sunat->estado = ['en_proceso' => false, 'cdr_zip' => 'cdr-baja', 'codigo' => 0, 'mensaje' => 'La Comunicacion de baja ha sido aceptada', 'notas' => []];
}

test('una factura no entregada se da de baja con una comunicación RA y la venta se anula cuando SUNAT la acepta', function () {
    [$sale, $factura, $unidad] = ventaConFactura();
    $factura->update(['enviado_at' => now()->subDays(2), 'cdr_path' => 'cdr/R-factura.zip']);

    $documento = app(VoidElectronicDocument::class)->handle($factura, 'Error en el cliente', noEntregado: true);

    expect($documento->sunat_estado)->toBe('baja_pendiente')
        ->and($documento->baja_ticket)->toBe('17000000001')
        ->and($this->sunat->resumenes)->toHaveCount(1)
        ->and($this->sunat->resumenes[0]['nombre'])->toMatch('/^\d{11}-RA-'.now()->format('Ymd').'-1$/')
        ->and($this->sunat->resumenes[0]['xml'])->toContain('VoidedDocuments')
        ->and($this->sunat->resumenes[0]['xml'])->toContain('Error en el cliente')
        ->and($sale->fresh()->estado)->toBe('confirmada');

    sunatAceptaLaBaja($this->sunat);
    $documento = app(VoidElectronicDocument::class)->consultar($documento);

    expect($documento->sunat_estado)->toBe('anulado')
        ->and($sale->fresh()->estado)->toBe('anulada')
        ->and($unidad->fresh()->estado)->toBe('disponible');
    Storage::disk('local')->assertExists("cdr/R-{$documento->baja_nombre}.zip");
});

test('una boleta se da de baja con un resumen diario RC en estado 3', function () {
    [, $boleta] = ventaConFactura(tipo: 'boleta');
    $boleta->update(['enviado_at' => now()->subDay()]);

    app(VoidElectronicDocument::class)->handle($boleta, 'Error en el monto', noEntregado: true);

    expect($this->sunat->resumenes[0]['nombre'])->toMatch('/^\d{11}-RC-'.now()->format('Ymd').'-1$/')
        ->and($this->sunat->resumenes[0]['xml'])->toContain('SummaryDocuments')
        ->and($this->sunat->resumenes[0]['xml'])->toContain('<cbc:ConditionCode>3</cbc:ConditionCode>')
        ->and($this->sunat->resumenes[0]['xml'])->toContain("{$boleta->serie}-{$boleta->correlativo}");
});

test('las bajas del mismo día llevan correlativo diario', function () {
    [, $primera] = ventaConFactura();
    [, $segunda] = ventaConFactura();

    app(VoidElectronicDocument::class)->handle($primera, 'Error', noEntregado: true);
    app(VoidElectronicDocument::class)->handle($segunda, 'Error', noEntregado: true);

    expect($this->sunat->resumenes[1]['nombre'])->toEndWith('RA-'.now()->format('Ymd').'-2');
});

test('si SUNAT rechaza la baja el comprobante vuelve a aceptado con el mensaje y la venta sigue vigente', function () {
    [$sale, $factura] = ventaConFactura();
    $this->sunat->estado = ['en_proceso' => false, 'cdr_zip' => 'cdr', 'codigo' => 2324, 'mensaje' => 'El comprobante ya fue informado', 'notas' => []];

    $documento = app(VoidElectronicDocument::class)->handle($factura, 'Error', noEntregado: true);

    expect($documento->sunat_estado)->toBe('aceptado')
        ->and($documento->baja_mensaje)->toContain('El comprobante ya fue informado')
        ->and($sale->fresh()->estado)->toBe('confirmada');
});

test('no se da de baja un comprobante sin declarar que no se entregó al cliente', function () {
    [, $factura] = ventaConFactura();

    expect(fn () => app(VoidElectronicDocument::class)->handle($factura, 'Error', noEntregado: false))
        ->toThrow(ValidationException::class);
    expect($this->sunat->resumenes)->toBe([]);
});

test('el plazo de baja son 7 días calendario desde el día siguiente a la recepción del CDR', function () {
    [, $enPlazo] = ventaConFactura();
    [, $vencido] = ventaConFactura();
    $enPlazo->update(['enviado_at' => now()->subDays(7)]);
    $vencido->update(['enviado_at' => now()->subDays(8)]);

    app(VoidElectronicDocument::class)->handle($enPlazo, 'Error', noEntregado: true);

    expect(fn () => app(VoidElectronicDocument::class)->handle($vencido, 'Error', noEntregado: true))
        ->toThrow(ValidationException::class, 'nota de crédito');
});

test('solo se da de baja un comprobante aceptado por SUNAT', function () {
    [, $factura] = ventaConFactura();
    $factura->update(['sunat_estado' => 'por_enviar']);

    expect(fn () => app(VoidElectronicDocument::class)->handle($factura, 'Error', noEntregado: true))
        ->toThrow(ValidationException::class);
});

test('la tarea programada consulta las bajas en proceso', function () {
    [$sale, $factura] = ventaConFactura();
    app(VoidElectronicDocument::class)->handle($factura, 'Error', noEntregado: true);
    sunatAceptaLaBaja($this->sunat);

    $this->artisan('billing:enviar-programados')->assertSuccessful();

    expect($factura->fresh()->sunat_estado)->toBe('anulado')
        ->and($sale->fresh()->estado)->toBe('anulada');
});

test('el vendedor pide la baja desde la venta y debe declarar que no la entregó', function () {
    [$sale, $factura] = ventaConFactura();
    $vendedor = User::find($sale->vendedor_id);
    $ruta = route('vendedor.facturacion.baja', ['current_team' => $vendedor->currentTeam, 'electronic_document' => $factura]);

    $this->actingAs($vendedor)->post($ruta, ['motivo' => 'Error en el cliente'])
        ->assertSessionHasErrors('no_entregado');

    $this->actingAs($vendedor)->post($ruta, ['motivo' => 'Error en el cliente', 'no_entregado' => true])
        ->assertSessionHasNoErrors();

    expect($factura->fresh()->sunat_estado)->toBe('baja_pendiente');
});

test('un vendedor sin permiso de anular no puede pedir la baja', function () {
    [$sale, $factura] = ventaConFactura();
    $vendedor = User::find($sale->vendedor_id);
    $vendedor->roles->first()->revokePermissionTo('billing.void');

    $this->actingAs($vendedor)
        ->post(route('vendedor.facturacion.baja', ['current_team' => $vendedor->currentTeam, 'electronic_document' => $factura]), [
            'motivo' => 'Error', 'no_entregado' => true,
        ])
        ->assertForbidden();
});

test('sin fecha de CDR el plazo de baja corre desde la fecha de emisión', function () {
    $doc = ElectronicDocument::factory()->make(['fecha_emision' => now()->subDays(3), 'enviado_at' => null]);

    expect($doc->fechaRecepcionCdr()->toDateString())->toBe(now()->subDays(3)->toDateString());
});
