<?php

use App\Actions\Billing\IssueCreditNote;
use App\Actions\Certificates\EmitirCertificadosDeVenta;
use App\Actions\Sales\ProcessSaleItem;
use App\Models\CashRegister;
use App\Models\Certificate;
use App\Models\Client;
use App\Models\Equipment;
use App\Models\InventoryMovement;
use App\Models\InventoryUnit;
use App\Models\Product;
use App\Models\Quote;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Service;
use App\Models\ServiceOrder;
use App\Models\User;
use Database\Seeders\CertificateTypeSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(CertificateTypeSeeder::class);
    [$this->sale, $this->factura, $this->unidad] = ventaConFactura();
    $this->vendedor = User::findOrFail($this->sale->vendedor_id);
    $this->vendedor->update(['sede_id' => $this->sale->sede_id]);
    CashRegister::factory()->create(['vendedor_id' => $this->vendedor->id]);
    $this->team = ['current_team' => $this->vendedor->currentTeam];
});

function segundaUnidad(Sale $sale): InventoryUnit
{
    $unidad = InventoryUnit::factory()->create(['product_id' => $sale->items()->first()->product_id, 'sede_almacen_id' => $sale->sede_id, 'estado' => 'disponible']);
    app(ProcessSaleItem::class)->handle($sale, ['tipo_linea' => 'unidad_nueva', 'numero_serie' => $unidad->numero_serie, 'product_id' => $unidad->product_id, 'cantidad' => 1, 'precio_unitario' => 100, 'subtotal' => 100]);

    return $unidad;
}

function anularParaCorregir(Sale $sale, $factura, bool $correccion = true): void
{
    aceptarNota(app(IssueCreditNote::class)->handle($factura, '01', 'Anulación de la operación', (float) $sale->total, $correccion ? ['correccion' => true] : []));
}

function rehacer(Sale $sale, array $unidades, array $cambios = []): Sale
{
    test()->actingAs(test()->vendedor)
        ->post(route('vendedor.ventas.store', test()->team), [
            'client_id' => $sale->client_id,
            'sede_id' => $sale->sede_id,
            'fecha' => now()->toDateString(),
            'destino' => 'local_cliente',
            'condicion_pago' => 'contado',
            'medio_pago' => 'efectivo',
            'comprobante_tipo' => 'nota_venta',
            'corrige_sale_id' => $sale->id,
            'emitir' => true,
            'items' => array_map(fn (InventoryUnit $u) => ['tipo_linea' => 'unidad_nueva', 'numero_serie' => $u->numero_serie, 'product_id' => $u->product_id, 'cantidad' => 1, 'precio_unitario' => 100], $unidades),
            ...$cambios,
        ])->assertSessionHasNoErrors();

    return Sale::query()->where('corrige_sale_id', $sale->id)->sole();
}

test('al corregir, el certificado no se anula mientras se rehace la venta', function () {
    $certificado = app(EmitirCertificadosDeVenta::class)->automaticos($this->sale)->first();

    anularParaCorregir($this->sale, $this->factura);

    expect($this->sale->fresh()->estado)->toBe('anulada')
        ->and($this->sale->fresh()->correccion_desde)->not->toBeNull()
        ->and($certificado->fresh()->estado)->toBe('vigente')
        ->and($this->unidad->fresh()->estado)->toBe('disponible')
        ->and(Equipment::where('numero_serie', $this->unidad->numero_serie)->sole()->estado)->not->toBe('baja');
});

test('el extintor que sigue conserva certificado, numero y vencimientos', function () {
    $certificado = app(EmitirCertificadosDeVenta::class)->automaticos($this->sale)->first();
    $equipo = Equipment::where('numero_serie', $this->unidad->numero_serie)->sole();
    $equipo->update(['fecha_venta' => '2026-09-01', 'proxima_fecha_atencion' => '2027-09-01']);
    $numero = $certificado->numero;
    anularParaCorregir($this->sale, $this->factura);

    $nueva = rehacer($this->sale, [$this->unidad]);

    expect($certificado->fresh())->estado->toBe('vigente')->numero->toBe($numero)->sale_id->toBe($nueva->id)->revision->toBe($certificado->revision)
        ->and($equipo->fresh()->fecha_venta->toDateString())->toBe('2026-09-01')
        ->and($equipo->fresh()->proxima_fecha_atencion->toDateString())->toBe('2027-09-01')
        ->and($this->sale->fresh()->correccion_desde)->toBeNull();
});

test('el extintor quitado sale del certificado y regresa al stock', function () {
    $segunda = segundaUnidad($this->sale);
    $certificado = app(EmitirCertificadosDeVenta::class)->automaticos($this->sale->fresh())->first();
    expect($certificado->certificateUnits()->count())->toBe(2);
    anularParaCorregir($this->sale, $this->factura);

    rehacer($this->sale, [$this->unidad]);

    $quitado = Equipment::where('numero_serie', $segunda->numero_serie)->sole();
    expect($certificado->fresh()->estado)->toBe('vigente')
        ->and($certificado->certificateUnits()->pluck('equipment_id')->all())->not->toContain($quitado->id)
        ->and($certificado->fresh()->revision)->toBeGreaterThan(0)
        ->and($segunda->fresh()->estado)->toBe('disponible')
        ->and($quitado->estado)->toBe('baja');
});

test('el extintor agregado entra al mismo certificado', function () {
    $certificado = app(EmitirCertificadosDeVenta::class)->automaticos($this->sale)->first();
    $nuevo = InventoryUnit::factory()->create(['product_id' => $this->unidad->product_id, 'sede_almacen_id' => $this->sale->sede_id, 'estado' => 'disponible']);
    anularParaCorregir($this->sale, $this->factura);

    rehacer($this->sale, [$this->unidad, $nuevo]);

    expect($certificado->fresh()->estado)->toBe('vigente')
        ->and($certificado->certificateUnits()->count())->toBe(2)
        ->and($nuevo->fresh()->estado)->toBe('vendido');
});

test('si cambia el cliente se anula lo anterior y sale certificado a su nombre', function () {
    $certificado = app(EmitirCertificadosDeVenta::class)->automaticos($this->sale)->first();
    $otro = Client::factory()->create();
    anularParaCorregir($this->sale, $this->factura);

    $nueva = rehacer($this->sale, [$this->unidad], ['client_id' => $otro->id]);

    $vigente = Certificate::where('sale_id', $nueva->id)->where('estado', 'vigente')->whereHas('certificateType', fn ($q) => $q->where('codigo', 'operatividad_garantia'))->sole();
    expect($certificado->fresh()->estado)->toBe('anulado')
        ->and($vigente->client_id)->toBe($otro->id)
        ->and($vigente->id)->not->toBe($certificado->id)
        ->and(Equipment::where('numero_serie', $this->unidad->numero_serie)->sole()->client_id)->toBe($otro->id);
});

test('la orden y la cotizacion pasan a la venta corregida', function () {
    $cotizacion = Quote::factory()->create(['client_id' => $this->sale->client_id, 'estado' => 'convertida']);
    $this->sale->update(['quote_id' => $cotizacion->id]);
    $orden = ServiceOrder::factory()->create(['client_id' => $this->sale->client_id, 'sede_id' => $this->sale->sede_id, 'sale_id' => $this->sale->id]);
    anularParaCorregir($this->sale, $this->factura);

    $nueva = rehacer($this->sale, [$this->unidad]);

    expect($orden->fresh()->sale_id)->toBe($nueva->id)
        ->and($nueva->fresh()->quote_id)->toBe($cotizacion->id)
        ->and($cotizacion->fresh()->sale->id)->toBe($nueva->id);
});

test('una correccion abandonada se cierra a los siete dias', function () {
    $certificado = app(EmitirCertificadosDeVenta::class)->automaticos($this->sale)->first();
    anularParaCorregir($this->sale, $this->factura);

    $this->artisan('ventas:cerrar-correcciones')->assertSuccessful();
    expect($certificado->fresh()->estado)->toBe('vigente');

    $this->travel(8)->days();
    $this->artisan('ventas:cerrar-correcciones')->assertSuccessful();

    expect($certificado->fresh()->estado)->toBe('anulado')
        ->and($this->sale->fresh()->correccion_desde)->toBeNull()
        ->and(Equipment::where('numero_serie', $this->unidad->numero_serie)->sole()->estado)->toBe('baja');
});

test('si el cliente devuelve todo los certificados se anulan al instante', function () {
    $certificado = app(EmitirCertificadosDeVenta::class)->automaticos($this->sale)->first();

    anularParaCorregir($this->sale, $this->factura, correccion: false);

    expect($certificado->fresh()->estado)->toBe('anulado')
        ->and($this->sale->fresh()->correccion_desde)->toBeNull()
        ->and($this->unidad->fresh()->estado)->toBe('disponible');
});

test('no se corrige una venta que no esta en correccion', function () {
    $this->actingAs($this->vendedor)
        ->post(route('vendedor.ventas.store', $this->team), [
            'client_id' => $this->sale->client_id, 'sede_id' => $this->sale->sede_id, 'fecha' => now()->toDateString(),
            'destino' => 'local_cliente', 'condicion_pago' => 'contado', 'medio_pago' => 'efectivo', 'comprobante_tipo' => 'nota_venta',
            'corrige_sale_id' => $this->sale->id,
            'items' => [['tipo_linea' => 'servicio', 'service_id' => Service::factory()->create()->id, 'cantidad' => 1, 'precio_unitario' => 50]],
        ])->assertSessionHasErrors('corrige_sale_id');
});

test('rehacer recibe el enlace solo si hay correccion en curso', function () {
    anularParaCorregir($this->sale, $this->factura);

    $this->actingAs($this->vendedor)
        ->get(route('vendedor.ventas.create', [...$this->team, 'rehacer' => $this->sale->id]))
        ->assertInertia(fn ($page) => $page->where('venta.corrige_sale_id', $this->sale->id));
});

test('la venta corregida y la anterior se muestran enlazadas', function () {
    anularParaCorregir($this->sale, $this->factura);
    $nueva = rehacer($this->sale, [$this->unidad]);

    $this->actingAs($this->vendedor)
        ->get(route('vendedor.ventas.show', [$this->vendedor->currentTeam, $nueva]))
        ->assertInertia(fn ($page) => $page->where('relacionados', fn ($filas) => collect($filas)->contains(fn ($fila) => $fila['tipo'] === 'Corrige a' && $fila['numero'] === $this->sale->numero_interno)));
});

function factura07(Sale $sale, $factura, array $lineas): void
{
    $factura->forceFill(['datos_emision' => ['cliente' => [], 'totales' => ['total' => (float) $sale->total], 'lineas' => array_map(fn (array $linea) => [
        'codigo' => 'ITEM', 'nombre' => 'Producto', 'unidad_medida' => 'NIU', 'tipo_afectacion_igv' => '10', 'precio_unitario' => $linea['precio'],
        'valor_unitario' => $linea['precio'], 'cantidad' => $linea['cantidad'], 'base' => $linea['precio'] * $linea['cantidad'], 'igv' => 0, 'subtotal' => $linea['precio'] * $linea['cantidad'],
    ], $lineas)]])->save();
}

function guantesEnVenta(Sale $sale, int $cantidad): Product
{
    $guantes = Product::factory()->create();
    SaleItem::factory()->create(['sale_id' => $sale->id, 'tipo_linea' => 'producto', 'product_id' => $guantes->id, 'inventory_unit_id' => null, 'cantidad' => $cantidad, 'precio_unitario' => 10, 'descuento' => 0, 'subtotal' => $cantidad * 10]);
    $salida = new InventoryMovement;
    $salida->product_id = $guantes->id;
    $salida->sede_id = $sale->sede_id;
    $salida->tipo = 'salida_venta';
    $salida->cantidad = -$cantidad;
    $salida->referencia_type = $sale->getMorphClass();
    $salida->referencia_id = $sale->id;
    $salida->save();

    return $guantes;
}

test('devolver parte de un producto regresa solo eso al stock y no se repite', function () {
    $guantes = guantesEnVenta($this->sale, 5);
    $this->sale->update(['total' => 150]);
    factura07($this->sale, $this->factura, [['precio' => 100, 'cantidad' => 1], ['precio' => 10, 'cantidad' => 5]]);
    $nota = fn (int $cantidad) => app(IssueCreditNote::class)->handle($this->factura->fresh(), '07', 'Devolución', $cantidad * 10, ['lineas' => [['indice' => 1, 'cantidad' => $cantidad]]]);

    aceptarNota($nota(2));

    expect($this->sale->fresh()->estado)->toBe('confirmada')
        ->and((int) InventoryMovement::where('referencia_id', $this->sale->id)->where('product_id', $guantes->id)->sum('cantidad'))->toBe(-3)
        ->and(fn () => $nota(4))->toThrow(ValidationException::class);

    aceptarNota($nota(3));

    expect((int) InventoryMovement::where('referencia_id', $this->sale->id)->where('product_id', $guantes->id)->sum('cantidad'))->toBe(0);
});

test('devolver un extintor por serie lo regresa y lo saca del certificado', function () {
    $segunda = segundaUnidad($this->sale);
    $this->sale->update(['total' => 200]);
    $certificado = app(EmitirCertificadosDeVenta::class)->automaticos($this->sale->fresh())->first();
    factura07($this->sale, $this->factura, [['precio' => 100, 'cantidad' => 2]]);

    expect(fn () => app(IssueCreditNote::class)->handle($this->factura, '07', 'Devolución', 100, ['lineas' => [['indice' => 0, 'cantidad' => 1]]]))
        ->toThrow(ValidationException::class);

    aceptarNota(app(IssueCreditNote::class)->handle($this->factura, '07', 'Devolución', 100, ['lineas' => [['indice' => 0, 'cantidad' => 1, 'series' => [$segunda->numero_serie]]]]));

    expect($segunda->fresh()->estado)->toBe('disponible')
        ->and($this->unidad->fresh()->estado)->toBe('vendido')
        ->and($certificado->certificateUnits()->count())->toBe(1)
        ->and(Equipment::where('numero_serie', $segunda->numero_serie)->sole()->estado)->toBe('baja')
        ->and($this->sale->fresh()->estado)->toBe('confirmada');
});

test('anular despues de una devolucion parcial no regresa dos veces', function () {
    $segunda = segundaUnidad($this->sale);
    $this->sale->update(['total' => 200]);
    factura07($this->sale, $this->factura, [['precio' => 100, 'cantidad' => 2]]);
    aceptarNota(app(IssueCreditNote::class)->handle($this->factura, '07', 'Devolución', 100, ['lineas' => [['indice' => 0, 'cantidad' => 1, 'series' => [$segunda->numero_serie]]]]));

    aceptarNota(app(IssueCreditNote::class)->handle($this->factura->fresh(), '01', 'Anulación', 100));

    expect($this->sale->fresh()->estado)->toBe('anulada')
        ->and(InventoryMovement::where('inventory_unit_id', $segunda->id)->where('tipo', 'ingreso')->count())->toBe(1)
        ->and($this->unidad->fresh()->estado)->toBe('disponible');
});
