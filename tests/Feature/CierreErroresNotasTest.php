<?php

use App\Actions\Certificates\EmitirCertificadosDeVenta;
use App\Models\CashRegister;
use App\Models\CertificateType;
use App\Models\ElectronicDocument;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Service;
use App\Models\User;
use App\Services\Certificates\CertificateDocumentData;
use Database\Seeders\CertificateTypeSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->vendedor = User::factory()->create();
    $this->vendedor->assignRole('Vendedor');
    CashRegister::factory()->create(['vendedor_id' => $this->vendedor->id]);
});

test('el deposito de detraccion no supera lo que queda tras las notas de credito', function () {
    $sale = Sale::factory()->create(['vendedor_id' => $this->vendedor->id, 'estado' => 'confirmada', 'condicion_pago' => 'contado', 'total' => 1180, 'codigo_detraccion_snapshot' => '022', 'monto_detraccion_snapshot' => 141.6]);
    $sale->payments()->create(['monto' => 1038.4, 'forma_pago' => 'transferencia', 'fecha' => today()]);
    ElectronicDocument::factory()->create(['sale_id' => $sale->id, 'tipo' => 'nota_credito', 'importe' => 100, 'sunat_estado' => 'aceptado']);

    $this->actingAs($this->vendedor)
        ->post(route('vendedor.ventas.detraccion.store', ['current_team' => $this->vendedor->currentTeam, 'sale' => $sale]), ['monto' => 141.6, 'fecha_deposito' => today()->toDateString(), 'numero_operacion' => 'BN-NOTA'])
        ->assertSessionHasErrors('monto');

    expect($sale->payments()->where('es_detraccion', true)->exists())->toBeFalse();
});

test('la venta y la cotizacion saben que productos y servicios no llevan IGV', function () {
    $exonerado = Product::factory()->create(['tipo_afectacion_igv' => '20']);
    $gravado = Product::factory()->create(['tipo_afectacion_igv' => '10']);
    $inafecto = Service::factory()->create(['tipo_afectacion_igv' => '30']);

    foreach (['vendedor.ventas.create', 'vendedor.cotizaciones.create'] as $ruta) {
        $this->actingAs($this->vendedor)
            ->get(route($ruta, $this->vendedor->currentTeam))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('noGravados.productos', fn ($ids) => collect($ids)->contains($exonerado->id) && ! collect($ids)->contains($gravado->id))
                ->where('noGravados.servicios', fn ($ids) => collect($ids)->contains($inafecto->id)));
    }
});

test('otro vendedor no abre el certificado de servicio de una venta ajena', function () {
    $otro = User::factory()->create();
    $otro->assignRole('Vendedor');
    $sale = Sale::factory()->create(['vendedor_id' => $this->vendedor->id, 'estado' => 'confirmada']);
    $this->seed(CertificateTypeSeeder::class);
    $tipo = CertificateType::query()->firstOrFail();

    $this->actingAs($otro)
        ->get(route('vendedor.ventas.certificado-servicio.create', ['current_team' => $otro->currentTeam, 'sale' => $sale, 'tipo' => $tipo->codigo]))
        ->assertNotFound();
});

test('envio sunat cuenta rechazos y excepciones del periodo elegido', function () {
    $sale = Sale::factory()->create(['vendedor_id' => $this->vendedor->id]);
    $documento = fn (string $estado, $fecha) => ElectronicDocument::factory()->create(['sale_id' => $sale->id, 'sunat_estado' => $estado, 'fecha_emision' => $fecha]);
    $documento('rechazado', today());
    $documento('excepcion', today());
    $documento('rechazado', today()->subMonths(3));
    $documento('observado', today()->subMonths(3));

    $this->actingAs($this->vendedor)
        ->get(route('vendedor.facturacion.index', ['current_team' => $this->vendedor->currentTeam, 'desde' => today()->toDateString(), 'hasta' => today()->toDateString()]))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('kpis.rechazados', 2)->where('kpis.observados', 0));
});

test('el panel limita el mes con la hora de Peru', function () {
    $this->travelTo(now('America/Lima')->setDate(2026, 10, 31)->setTime(21, 0));
    $gerente = User::factory()->create();
    $gerente->assignRole('Gerente');

    $this->actingAs($gerente)
        ->get(route('gerente.dashboard', $gerente->currentTeam))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('mesActual', '2026-10'));
});

test('el xml no se descarga antes de enviarlo a sunat', function () {
    $sale = Sale::factory()->create(['vendedor_id' => $this->vendedor->id]);
    $documento = ElectronicDocument::factory()->create(['sale_id' => $sale->id, 'sunat_estado' => 'por_enviar']);

    $this->actingAs($this->vendedor)
        ->get(route('vendedor.facturacion.xml', ['current_team' => $this->vendedor->currentTeam, 'electronic_document' => $documento]))
        ->assertNotFound();
});

test('el certificado no muestra vigencia, orden ni codigo interno', function () {
    $this->seed(CertificateTypeSeeder::class);
    [$sale] = ventaConFactura();
    $certificado = app(EmitirCertificadosDeVenta::class)->automaticos($sale)->first();

    $datos = app(CertificateDocumentData::class)->desde($certificado->fresh());

    expect($datos['certificado']['vigencia'])->toBeNull()
        ->and($datos['certificado']['orden'])->toBeNull()
        ->and(array_column($datos['tipo']['columnas'], 'clave'))->not->toContain('codigo_interno');
});

test('un certificado con nuevos y recargados dice la atencion de cada fila', function () {
    $this->seed(CertificateTypeSeeder::class);
    [$sale] = ventaConFactura();
    $certificado = app(EmitirCertificadosDeVenta::class)->automaticos($sale)->first();
    $certificado->update(['tipo_atencion' => 'nuevo / recarga']);

    $datos = app(CertificateDocumentData::class)->desde($certificado->fresh());

    expect(array_column($datos['tipo']['columnas'], 'clave'))->toContain('atencion')
        ->and($datos['filas'][0]['atencion'])->toBe('Nuevo');
});
