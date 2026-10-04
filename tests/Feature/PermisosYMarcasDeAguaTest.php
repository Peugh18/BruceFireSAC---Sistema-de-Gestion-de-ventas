<?php

use App\Models\CashRegister;
use App\Models\Certificate;
use App\Models\Client;
use App\Models\ElectronicDocument;
use App\Models\InventoryUnit;
use App\Models\Product;
use App\Models\Quote;
use App\Models\Sale;
use App\Models\Sede;
use App\Models\ServiceOrder;
use App\Models\User;
use App\Services\Billing\ComprobantePdfService;
use App\Services\Certificates\CertificatePdfService;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->sede = Sede::factory()->mixta()->create();
    $this->otraSede = Sede::factory()->mixta()->create();
    $this->product = Product::factory()->create(['precio_venta' => 100]);
    $this->vendedor = User::factory()->create(['sede_id' => $this->sede->id]);
    $this->vendedor->assignRole('Vendedor');
    CashRegister::factory()->create(['vendedor_id' => $this->vendedor->id]);
    $this->team = ['current_team' => $this->vendedor->currentTeam];
});

/**
 * @return array<string, mixed>
 */
function ventaDePrueba(array $cambios = []): array
{
    $unit = InventoryUnit::factory()->create([
        'product_id' => test()->product->id,
        'sede_almacen_id' => test()->sede->id,
        'estado' => 'disponible',
    ]);

    return [
        'client_id' => Client::factory()->create()->id,
        'fecha' => now()->toDateString(),
        'destino' => 'local_cliente',
        'condicion_pago' => 'contado',
        'medio_pago' => 'efectivo',
        'comprobante_tipo' => 'nota_venta',
        'items' => [['tipo_linea' => 'unidad_nueva', 'numero_serie' => $unit->numero_serie, 'product_id' => test()->product->id, 'cantidad' => 1, 'precio_unitario' => 100]],
        ...$cambios,
    ];
}

test('ninguna linea puede quedar en cero por un descuento total', function () {
    $datos = ventaDePrueba();
    $datos['items'][0]['descuento'] = 100;

    $this->actingAs($this->vendedor)
        ->post(route('vendedor.ventas.store', $this->team), $datos)
        ->assertSessionHasErrors('items.0.descuento');

    $datos['items'][0]['descuento'] = 99;

    $this->actingAs($this->vendedor)
        ->post(route('vendedor.ventas.store', $this->team), $datos)
        ->assertSessionHasNoErrors();
});

test('no se cobra una orden de servicio de otra sede', function () {
    $datos = ventaDePrueba();
    $orden = ServiceOrder::factory()->create(['sede_id' => $this->otraSede->id, 'client_id' => $datos['client_id']]);

    $this->actingAs($this->vendedor)
        ->post(route('vendedor.ventas.store', $this->team), [...$datos, 'service_order_id' => $orden->id])
        ->assertSessionHasErrors('service_order_id');

    expect(Sale::count())->toBe(0)
        ->and($orden->fresh()->sale_id)->toBeNull();
});

test('no se convierte en venta una cotizacion de otra sede', function () {
    $datos = ventaDePrueba();
    $cotizacion = Quote::factory()->create([
        'sede_id' => $this->otraSede->id,
        'client_id' => $datos['client_id'],
        'estado' => 'aceptada',
    ]);

    $this->actingAs($this->vendedor)
        ->post(route('vendedor.ventas.store', $this->team), [...$datos, 'quote_id' => $cotizacion->id])
        ->assertSessionHasErrors('quote_id');

    expect(Sale::count())->toBe(0)
        ->and($cotizacion->fresh()->estado)->toBe('aceptada');
});

test('un vendedor no descarga ni corrige comprobantes de la venta de otro', function () {
    $companero = User::factory()->create(['sede_id' => $this->sede->id]);
    $companero->assignRole('Vendedor');
    $factura = ElectronicDocument::factory()->create([
        'sale_id' => Sale::factory()->create(['sede_id' => $this->sede->id, 'vendedor_id' => $companero->id, 'estado' => 'confirmada']),
        'sunat_estado' => 'aceptado',
    ]);

    $this->actingAs($this->vendedor)
        ->get(route('vendedor.facturacion.pdf', [...$this->team, 'electronic_document' => $factura]))
        ->assertNotFound();

    $this->actingAs($this->vendedor)
        ->post(route('vendedor.notas-credito.store', $this->team), [
            'electronic_document_id' => $factura->id,
            'motivo_catalogo' => '04',
            'detalle' => 'Descuento',
            'importe' => 10,
        ])
        ->assertNotFound();

    expect(ElectronicDocument::where('tipo', 'nota_credito')->count())->toBe(0);
});

test('el pdf de una venta anulada lleva la marca de agua ANULADO y se redibuja', function () {
    $sale = Sale::factory()->create(['estado' => 'confirmada', 'comprobante_tipo' => 'nota_venta', 'numero_nota_venta' => 'NV-0099']);
    $pdf = app(ComprobantePdfService::class);
    $documento = new ElectronicDocument(['tipo' => 'nota_venta']);
    $documento->setRelation('sale', $sale);

    $firmaVigente = $pdf->firmaDeDiseno(ElectronicDocument::factory()->create(['sale_id' => $sale->id]));

    expect(view('pdf.comprobante', ['anulado' => false] + (fn () => $this->datos($documento, null))->call($pdf))->render())
        ->not->toContain('class="marca-anulado"');

    $sale->update(['estado' => 'anulada']);
    $documento->setRelation('sale', $sale->fresh());

    expect(view('pdf.comprobante', (fn () => $this->datos($documento, null))->call($pdf))->render())
        ->toContain('class="marca-anulado"')
        ->and($pdf->firmaDeDiseno(ElectronicDocument::where('sale_id', $sale->id)->sole()))->not->toBe($firmaVigente);
});

test('un certificado anulado o vencido lleva su sello en rojo', function () {
    $servicio = app(CertificatePdfService::class);

    expect($servicio->selloDeEstado(Certificate::factory()->make()))->toBeNull()
        ->and($servicio->selloDeEstado(Certificate::factory()->make(['estado' => 'anulado'])))->toBe('ANULADO')
        ->and($servicio->selloDeEstado(Certificate::factory()->make(['fecha_vigencia_hasta' => today()->subDay()])))->toBe('VENCIDO')
        ->and($servicio->selloDeEstado(Certificate::factory()->make(['estado' => 'vencido'])))->toBe('VENCIDO');
});
