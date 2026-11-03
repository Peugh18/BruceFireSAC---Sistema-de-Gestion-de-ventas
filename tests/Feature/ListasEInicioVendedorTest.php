<?php

use App\Actions\Billing\AplicarNotaAlSaldo;
use App\Actions\Billing\IssueCreditNote;
use App\Models\ElectronicDocument;
use App\Models\Installment;
use App\Models\Quote;
use App\Models\Sale;
use App\Models\SalePayment;
use App\Models\User;
use App\Services\AuditLogger;
use Carbon\Carbon;
use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Carbon::setTestNow('2026-10-20 10:00:00');
});

afterEach(function () {
    Carbon::setTestNow();
});

function vendedorDeLista(): User
{
    $user = User::factory()->create();
    $user->assignRole('Vendedor');

    return $user;
}

test('ventas abre en este mes aunque hoy no haya ventas', function () {
    $user = vendedorDeLista();
    $delMes = Sale::factory()->create(['vendedor_id' => $user->id, 'fecha' => '2026-10-02', 'estado' => 'confirmada']);
    Sale::factory()->create(['vendedor_id' => $user->id, 'fecha' => '2026-09-30', 'estado' => 'confirmada']);

    $this->actingAs($user)
        ->get(route('vendedor.ventas.index', $user->currentTeam))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('filters.desde', '2026-10-01')
            ->where('filters.hasta', '2026-10-20')
            ->has('sales.data', 1)
            ->where('sales.data.0.id', $delMes->id));
});

test('un filtro solo con fecha inicial llega hasta hoy', function () {
    $user = vendedorDeLista();
    Sale::factory()->create(['vendedor_id' => $user->id, 'fecha' => '2026-08-15', 'estado' => 'borrador']);

    $this->actingAs($user)
        ->get(route('vendedor.ventas.index', ['current_team' => $user->currentTeam, 'desde' => '2026-01-01', 'estado' => 'borrador']))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('filters.hasta', '2026-10-20')
            ->has('sales.data', 1));
});

test('la cotizacion no enlaza una venta que el vendedor no puede abrir', function () {
    $user = vendedorDeLista();
    $otro = vendedorDeLista();
    $sale = Sale::factory()->create(['vendedor_id' => $otro->id, 'estado' => 'confirmada', 'numero_interno' => 'VTA-9001']);
    $propia = Sale::factory()->create(['vendedor_id' => $user->id, 'estado' => 'confirmada', 'numero_interno' => 'VTA-9002']);
    $ajena = Quote::factory()->create(['vendedor_id' => $otro->id, 'estado' => 'convertida']);
    $mia = Quote::factory()->create(['vendedor_id' => $user->id, 'estado' => 'convertida']);
    $sale->update(['quote_id' => $ajena->id]);
    $propia->update(['quote_id' => $mia->id]);

    $this->actingAs($user)
        ->get(route('vendedor.cotizaciones.index', $user->currentTeam))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('quotes.data', function ($filas) use ($ajena, $mia, $propia) {
            $porId = collect($filas)->keyBy('id');

            return $porId[$ajena->id]['venta'] === ['id' => null, 'numero' => 'VTA-9001']
                && $porId[$mia->id]['venta'] === ['id' => $propia->id, 'numero' => 'VTA-9002'];
        }));
});

test('los documentos relacionados no repiten las notas del panel de notas', function () {
    $user = vendedorDeLista();
    [$sale, $factura] = ventaConFactura();
    $sale->update(['vendedor_id' => $user->id]);
    $user->update(['sede_id' => $sale->sede_id]);
    $nota = app(IssueCreditNote::class)->handle($factura, '04', 'Rebaja', 10);

    $this->actingAs($user)
        ->get(route('vendedor.ventas.show', [$user->currentTeam, $sale]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('relacionados', fn ($filas) => ! collect($filas)->pluck('tipo')->contains('Nota de crédito')
            && collect($filas)->pluck('tipo')->contains('Factura'))
            ->where('sale.electronic_documents', fn ($documentos) => collect($documentos)->pluck('id')->contains($nota->id)));
});

test('inicio cuenta todos los clientes por renovar aunque liste cinco', function () {
    $user = vendedorDeLista();

    $this->actingAs($user)
        ->get(route('vendedor.dashboard', $user->currentTeam))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('por_renovar', 0));
});

test('el detalle de venta resume saldo, cobros y notas aceptadas', function () {
    $user = vendedorDeLista();
    $sale = Sale::factory()->create(['vendedor_id' => $user->id, 'estado' => 'confirmada', 'total' => 100]);
    $cuota = Installment::factory()->create(['sale_id' => $sale->id, 'monto' => 100, 'monto_acreditado' => 10, 'estado' => 'parcial']);
    SalePayment::factory()->create(['sale_id' => $sale->id, 'installment_id' => $cuota->id, 'monto' => 30]);
    SalePayment::factory()->create(['sale_id' => $sale->id, 'installment_id' => $cuota->id, 'monto' => 5])->delete();
    ElectronicDocument::factory()->create(['sale_id' => $sale->id, 'tipo' => 'nota_credito', 'importe' => 10, 'sunat_estado' => 'aceptado']);
    ElectronicDocument::factory()->create(['sale_id' => $sale->id, 'tipo' => 'nota_credito', 'importe' => 7, 'sunat_estado' => 'rechazado']);

    $this->actingAs($user)
        ->get(route('vendedor.ventas.show', [$user->currentTeam, $sale]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('resumen.total', 100)
            ->where('resumen.notas_credito', 10)
            ->where('resumen.notas_debito', 0)
            ->where('resumen.cobrado', 30)
            ->where('resumen.devuelto', 0)
            ->where('resumen.pendiente', 60));
});

test('el detalle de venta muestra su historial sin datos de otras ventas', function () {
    $user = vendedorDeLista();
    $sale = Sale::factory()->create(['vendedor_id' => $user->id, 'estado' => 'confirmada']);
    $otra = Sale::factory()->create(['vendedor_id' => $user->id, 'estado' => 'confirmada']);
    AuditLogger::log(action: 'venta.creada', entity: $sale, newValues: ['numero_interno' => 'VTA-7001'], userId: $user->id);
    AuditLogger::log(action: 'venta.anulada', entity: $otra, userId: $user->id);

    $this->actingAs($user)
        ->get(route('vendedor.ventas.show', [$user->currentTeam, $sale]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('historial', 1)
            ->where('historial.0.texto', 'Se registró una venta (VTA-7001)')
            ->where('historial.0.usuario', $user->name));
});

test('la pantalla de nota recibe el saldo y lo que se puede devolver sin contar la detraccion', function () {
    $user = vendedorDeLista();
    [$sale, $factura] = ventaConFactura();
    $sale->update(['vendedor_id' => $user->id, 'total' => 100]);
    $user->update(['sede_id' => $sale->sede_id]);
    $sale->payments()->delete();
    SalePayment::factory()->create(['sale_id' => $sale->id, 'monto' => 88, 'forma_pago' => 'efectivo', 'es_detraccion' => false]);
    SalePayment::factory()->create(['sale_id' => $sale->id, 'monto' => 12, 'forma_pago' => 'deposito', 'es_detraccion' => true, 'numero_operacion' => 'BN-1']);

    $this->actingAs($user)
        ->get(route('vendedor.ventas.nota', ['current_team' => $user->currentTeam, 'sale' => $sale, 'tipo' => 'credito']))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('resumen.cobrado', 100)
            ->where('resumen.devolvible', 88)
            ->where('resumen.pendiente', 0));
});

test('el excedente de una nota no devuelve por caja la detraccion', function () {
    $sale = Sale::factory()->create(['estado' => 'confirmada', 'total' => 100]);
    SalePayment::factory()->create(['sale_id' => $sale->id, 'monto' => 88, 'forma_pago' => 'efectivo', 'es_detraccion' => false]);
    SalePayment::factory()->create(['sale_id' => $sale->id, 'monto' => 12, 'forma_pago' => 'deposito', 'es_detraccion' => true, 'numero_operacion' => 'BN-2']);
    $nota = ElectronicDocument::factory()->create(['sale_id' => $sale->id, 'tipo' => 'nota_credito', 'importe' => 95, 'sunat_estado' => 'aceptado']);

    app(AplicarNotaAlSaldo::class)->handle($nota);

    expect((float) $sale->refunds()->sum('monto'))->toBe(88.0)
        ->and($sale->refunds()->where('forma_pago', 'deposito')->exists())->toBeFalse();
});

test('envio sunat muestra primero lo que hay que resolver', function () {
    $user = vendedorDeLista();
    $sale = Sale::factory()->create(['vendedor_id' => $user->id]);
    $documento = fn (string $estado, int $correlativo) => ElectronicDocument::factory()->create(['sale_id' => $sale->id, 'tipo' => 'factura', 'serie' => 'F001', 'correlativo' => $correlativo, 'sunat_estado' => $estado, 'fecha_emision' => today()]);
    $documento('aceptado', 1);
    $documento('rechazado', 2);
    $documento('observado', 3);
    $documento('por_enviar', 4);

    $this->actingAs($user)
        ->get(route('vendedor.facturacion.index', $user->currentTeam))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('documents.data', fn ($filas) => collect($filas)->pluck('correlativo')->all() === [2, 4, 3, 1]));
});
