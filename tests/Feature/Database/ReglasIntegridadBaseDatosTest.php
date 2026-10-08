<?php

use App\Actions\Certificates\IssueCertificate;
use App\Actions\Sales\ProcessSaleItem;
use App\Models\AuditLog;
use App\Models\CashRegister;
use App\Models\Certificate;
use App\Models\CertificateType;
use App\Models\CertificateUnit;
use App\Models\Client;
use App\Models\Deficiency;
use App\Models\DeficiencyAuthorization;
use App\Models\DocumentSeries;
use App\Models\ElectronicDocument;
use App\Models\Equipment;
use App\Models\Evidencia;
use App\Models\Installment;
use App\Models\InventoryMovement;
use App\Models\InventoryTransfer;
use App\Models\InventoryUnit;
use App\Models\MlClienteHistorico;
use App\Models\MlComprobanteHistorico;
use App\Models\MlLineaHistorica;
use App\Models\MlProductoHistorico;
use App\Models\NoteRequest;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductLot;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SalePayment;
use App\Models\Sede;
use App\Models\Service;
use App\Models\ServiceOrder;
use App\Models\ServiceOrderEvent;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

it('impide borrar un cliente que tiene ventas y conserva ambos registros', function () {
    $client = Client::factory()->create();
    $sale = Sale::factory()->create(['client_id' => $client->id]);

    expect(fn () => $client->delete())->toThrow(QueryException::class);

    $this->assertDatabaseHas('clients', ['id' => $client->id]);
    $this->assertDatabaseHas('sales', ['id' => $sale->id]);
});

it('registra una entrega en mostrador con el tipo de evento aceptado por la base', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $seller = User::factory()->create();
    $seller->assignRole('Vendedor');
    $order = ServiceOrder::factory()->create(['sede_id' => $seller->sede_id, 'estado' => 'listo_entrega']);

    $this->actingAs($seller)
        ->post(route('vendedor.ordenes-servicio.entrega-mostrador.store', [
            'current_team' => $seller->currentTeam,
            'service_order' => $order,
        ]), [
            'receptor_nombre' => 'Cliente de prueba',
            'receptor_dni' => '12345678',
            'conformidad_aceptada' => true,
        ])
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('service_order_events', ['service_order_id' => $order->id, 'tipo' => 'entrega_registrada']);
});

it('rechaza una cantidad cero mediante el check de MySQL', function () {
    if (DB::getDriverName() !== 'mysql') {
        $this->markTestSkipped('SQLite omite CHECK agregados a tablas existentes porque no existe un ALTER portable.');
    }

    $item = SaleItem::factory()->make(['cantidad' => 0, 'subtotal' => 0]);

    expect(fn () => $item->save())->toThrow(QueryException::class);
});

it('impide vender dos veces una misma unidad de inventario', function () {
    $sede = Sede::factory()->almacen()->create();
    $unit = InventoryUnit::factory()->create(['sede_almacen_id' => $sede->id, 'estado' => 'vendido']);
    $sale = Sale::factory()->create(['sede_id' => $sede->id]);

    expect(fn () => app(ProcessSaleItem::class)->handle($sale, [
        'tipo_linea' => 'unidad_nueva',
        'numero_serie' => $unit->numero_serie,
        'product_id' => $unit->product_id,
        'cantidad' => 1,
        'precio_unitario' => 100,
        'subtotal' => 100,
    ]))->toThrow(ValidationException::class, 'ya no está disponible');
});

it('impide repetir el numero de cuota dentro de una venta', function () {
    $sale = Sale::factory()->create();
    Installment::factory()->create(['sale_id' => $sale->id, 'numero_cuota' => 1]);

    expect(fn () => Installment::factory()->create(['sale_id' => $sale->id, 'numero_cuota' => 1]))
        ->toThrow(QueryException::class);
});

it('impide repetir un extintor dentro del mismo certificado', function () {
    $certificate = Certificate::factory()->create();
    $equipment = Equipment::factory()->create(['client_id' => $certificate->client_id]);
    CertificateUnit::factory()->create(['certificate_id' => $certificate->id, 'equipment_id' => $equipment->id]);

    expect(fn () => CertificateUnit::factory()->create(['certificate_id' => $certificate->id, 'equipment_id' => $equipment->id]))
        ->toThrow(QueryException::class);
});

it('usa el servicio relacionado y elimina las columnas duplicadas de tercera forma normal', function () {
    $service = Service::factory()->create(['nombre' => 'Inspección anual']);
    $order = ServiceOrder::factory()->create(['service_id' => $service->id]);

    expect($order->service->nombre)->toBe('Inspección anual')
        ->and(Schema::hasColumn('service_orders', 'tipo_servicio'))->toBeFalse()
        ->and(Schema::hasColumn('company_settings', 'firma_tecnico_nombre'))->toBeFalse()
        ->and(Schema::hasColumn('company_settings', 'firma_administrador_nombre'))->toBeFalse()
        ->and(Schema::hasColumn('company_settings', 'firma_ingeniero_nombre'))->toBeFalse()
        ->and(Schema::hasColumn('company_settings', 'firma_ingeniero_cip'))->toBeFalse();
});

it('toma el medio de pago desde sale payments para una venta emitida', function () {
    $sale = Sale::factory()->create(['medio_pago' => 'efectivo', 'estado' => 'confirmada']);
    SalePayment::factory()->create(['sale_id' => $sale->id, 'forma_pago' => 'yape']);

    expect($sale->medioPagoTexto())->toBe('Yape');
});

it('sin cobros la cabecera de la venta es el unico respaldo historico del medio de pago', function () {
    $sale = Sale::factory()->create(['medio_pago' => 'yape', 'numero_operacion' => 'YAPE-001', 'estado' => 'confirmada']);

    expect($sale->medioPagoTexto())->toBe('Yape');
});

it('el cobro conserva su hora de registro en created_at', function () {
    $sale = Sale::factory()->create();
    $antes = now()->subMinute();

    $pago = SalePayment::create(['sale_id' => $sale->id, 'forma_pago' => 'efectivo', 'monto' => 10, 'fecha' => today()]);

    // fecha guarda el día de negocio y created_at la hora real del cobro.
    expect($pago->fecha->toDateString())->toBe(today()->toDateString())
        ->and($pago->created_at->between($antes, now()))->toBeTrue();
});

it('la categoria de productos y servicios es una clave foranea real', function () {
    $categoria = ProductCategory::factory()->create();
    Product::factory()->create(['categoria' => $categoria->clave]);
    Service::factory()->create(['categoria' => $categoria->clave]);

    // Una categoría que no existe no cuela ni en productos ni en servicios...
    expect(fn () => Product::factory()->create(['categoria' => 'inexistente']))->toThrow(QueryException::class)
        ->and(fn () => Service::factory()->create(['categoria' => 'inexistente']))->toThrow(QueryException::class);

    // ...la clave de una categoría en uso no se renombra ni se borra...
    $categoria->clave = 'renombrada';
    expect(fn () => $categoria->save())->toThrow(QueryException::class)
        ->and(fn () => $categoria->delete())->toThrow(QueryException::class);

    // ...pero el nombre visible sí se puede cambiar, y una categoría sin uso
    // sí se puede borrar.
    $categoria->refresh()->update(['nombre' => 'Categoría renombrada']);
    $libre = ProductCategory::factory()->create();
    expect($categoria->fresh()->nombre)->toBe('Categoría renombrada')
        ->and(fn () => $libre->delete())->not->toThrow(QueryException::class);
});

it('el historial de ML es inmutable y sus claves no se borran en cascada', function () {
    $cliente = MlClienteHistorico::create(['documento' => '20555555555', 'nombre' => 'Cliente viejo']);
    $comprobante = MlComprobanteHistorico::create(['comprobante' => 'F001-1', 'tipo_doc' => 'F', 'fecha' => '2025-01-10', 'documento_cliente' => $cliente->documento, 'archivo_origen' => 'ENERO.xlsx']);
    $producto = MlProductoHistorico::create(['nombre' => 'EXTINTOR PQS 6KG', 'categoria' => 'extintor']);
    MlLineaHistorica::create(['comprobante' => $comprobante->comprobante, 'ml_producto_id' => $producto->id, 'cantidad' => 1, 'total' => 70]);

    expect(fn () => $cliente->delete())->toThrow(QueryException::class)
        ->and(fn () => $comprobante->delete())->toThrow(QueryException::class)
        ->and(fn () => $producto->delete())->toThrow(QueryException::class)
        ->and(MlLineaHistorica::count())->toBe(1);
});

it('acepta todos los tipos de eventos declarados por el codigo', function (string $type) {
    $event = ServiceOrderEvent::factory()->create(['tipo' => $type]);

    expect($event->tipo)->toBe($type);
})->with([
    'creada',
    'recibida',
    'deficiencia_detectada',
    'notificacion_vendedor',
    'autorizacion_registrada',
    'trabajo_completado',
    'entrega_registrada',
    'otro',
]);

it('no emite un certificado para un cliente distinto al de su venta', function () {
    $sale = Sale::factory()->create(['client_id' => Client::factory()->create()->id]);
    $otroCliente = Client::factory()->create();
    $tipo = CertificateType::factory()->create();

    expect(fn () => app(IssueCertificate::class)->handle($tipo, $otroCliente, [], saleId: $sale->id))
        ->toThrow(ValidationException::class, 'mismo cliente');
});

it('las sedes no se borran: se desactivan', function () {
    $sede = Sede::factory()->almacen()->create();
    Sale::factory()->create(['sede_id' => $sede->id]);

    expect(fn () => $sede->delete())->toThrow(QueryException::class);
    $this->assertDatabaseHas('sedes', ['id' => $sede->id]);
});

it('las lineas historicas conservan su producto y su servicio', function () {
    $product = Product::factory()->create();
    $service = Service::factory()->create();
    SaleItem::factory()->create(['product_id' => $product->id, 'service_id' => null]);
    SaleItem::factory()->forService($service)->create();

    expect(fn () => $product->delete())->toThrow(QueryException::class)
        ->and(fn () => $service->delete())->toThrow(QueryException::class);
});

it('un usuario con auditoria o kardex no se borra y el log conserva al actor', function () {
    $user = User::factory()->create();
    AuditLog::factory()->create(['user_id' => $user->id]);

    expect(fn () => $user->delete())->toThrow(QueryException::class);
    $this->assertDatabaseHas('audit_logs', ['user_id' => $user->id]);
});

it('las ventas no se borran: certificados y ordenes de servicio las protegen', function () {
    $conCertificado = Sale::factory()->create();
    Certificate::factory()->create(['sale_id' => $conCertificado->id]);

    $conOrden = Sale::factory()->create();
    ServiceOrder::factory()->create(['sale_id' => $conOrden->id]);

    expect(fn () => $conCertificado->delete())->toThrow(QueryException::class)
        ->and(fn () => $conOrden->delete())->toThrow(QueryException::class);
});

it('las evidencias protegen la orden de servicio como sus hermanas', function () {
    $order = ServiceOrder::factory()->create();
    Evidencia::factory()->create(['service_order_id' => $order->id]);

    expect(fn () => $order->delete())->toThrow(QueryException::class);
});

it('el kardex no pierde su lote ni su producto', function () {
    $product = Product::factory()->create();
    $sede = Sede::factory()->almacen()->create();
    $lot = ProductLot::create([
        'product_id' => $product->id,
        'sede_id' => $sede->id,
        'lote' => 'L-'.fake()->unique()->numerify('####'),
        'fecha_vencimiento' => now()->addYear()->toDateString(),
    ]);
    InventoryMovement::create([
        'product_id' => $product->id,
        'sede_id' => $sede->id,
        'product_lot_id' => $lot->id,
        'tipo' => 'ingreso',
        'cantidad' => 5,
    ]);

    expect(fn () => $lot->delete())->toThrow(QueryException::class)
        ->and(fn () => $product->delete())->toThrow(QueryException::class)
        ->and(fn () => $sede->delete())->toThrow(QueryException::class);
});

it('un doble submit no duplica el movimiento de kardex de una unidad', function () {
    $sede = Sede::factory()->almacen()->create();
    $unit = InventoryUnit::factory()->create(['sede_almacen_id' => $sede->id, 'estado' => 'vendido']);
    $sale = Sale::factory()->create(['sede_id' => $sede->id]);
    $datos = [
        'inventory_unit_id' => $unit->id,
        'product_id' => $unit->product_id,
        'sede_id' => $sede->id,
        'tipo' => 'salida_venta',
        'cantidad' => -1,
        'referencia_type' => $sale->getMorphClass(),
        'referencia_id' => $sale->id,
    ];

    InventoryMovement::create($datos);

    expect(fn () => InventoryMovement::create($datos))->toThrow(QueryException::class);

    // La reversión del movimiento usa otro tipo y sí entra.
    InventoryMovement::create([...$datos, 'tipo' => 'ingreso', 'cantidad' => 1]);
    expect(InventoryMovement::where('inventory_unit_id', $unit->id)->count())->toBe(2);
});

it('un doble submit no duplica un cobro con el mismo numero de operacion', function () {
    $sale = Sale::factory()->create();
    $datos = ['sale_id' => $sale->id, 'forma_pago' => 'transferencia', 'monto' => 100, 'numero_operacion' => 'OP-987654', 'fecha' => today()];

    SalePayment::create($datos);

    expect(fn () => SalePayment::create($datos))->toThrow(QueryException::class);

    // El mismo número de operación en otra cuota es otra imputación válida...
    $otraVenta = Sale::factory()->create();
    SalePayment::create(['sale_id' => $otraVenta->id, 'forma_pago' => 'transferencia', 'monto' => 100, 'numero_operacion' => 'OP-987654', 'fecha' => today()]);

    // ... y el efectivo sin número de operación se puede pagar por partes.
    SalePayment::create(['sale_id' => $sale->id, 'forma_pago' => 'efectivo', 'monto' => 50, 'fecha' => today()]);
    SalePayment::create(['sale_id' => $sale->id, 'forma_pago' => 'efectivo', 'monto' => 50, 'fecha' => today()]);

    expect(SalePayment::count())->toBe(4);
});

it('un cobro anulado libera su numero de operacion', function () {
    $sale = Sale::factory()->create();
    $datos = ['sale_id' => $sale->id, 'forma_pago' => 'yape', 'monto' => 80, 'numero_operacion' => 'YAPE-1', 'fecha' => today()];

    $pago = SalePayment::create($datos);
    $pago->anular('Se registró dos veces', null);

    SalePayment::create($datos);
    expect(SalePayment::withTrashed()->count())->toBe(2);
});

it('una deficiencia tiene una sola autorizacion', function () {
    $deficiency = Deficiency::factory()->create();
    DeficiencyAuthorization::factory()->create(['deficiency_id' => $deficiency->id]);

    expect(fn () => DeficiencyAuthorization::factory()->create(['deficiency_id' => $deficiency->id]))
        ->toThrow(QueryException::class);
});

it('un extintor no se repite en el mismo certificado aunque no tenga equipo', function () {
    $certificate = Certificate::factory()->create();

    CertificateUnit::factory()->create(['certificate_id' => $certificate->id, 'equipment_id' => null, 'numero_serie_snapshot' => 'SN-REPETIDA']);

    expect(fn () => CertificateUnit::factory()->create(['certificate_id' => $certificate->id, 'equipment_id' => null, 'numero_serie_snapshot' => 'SN-REPETIDA']))
        ->toThrow(QueryException::class);

    // Las filas sin serie no chocan entre sí.
    CertificateUnit::factory()->create(['certificate_id' => $certificate->id, 'equipment_id' => null, 'numero_serie_snapshot' => '']);
    CertificateUnit::factory()->create(['certificate_id' => $certificate->id, 'equipment_id' => null, 'numero_serie_snapshot' => '']);
    expect(CertificateUnit::where('certificate_id', $certificate->id)->count())->toBe(3);
});

it('un vendedor tiene un solo turno de caja abierto', function () {
    $vendedor = User::factory()->create();
    CashRegister::factory()->create(['vendedor_id' => $vendedor->id, 'estado' => 'abierto']);

    expect(fn () => CashRegister::factory()->create(['vendedor_id' => $vendedor->id, 'estado' => 'abierto']))
        ->toThrow(QueryException::class);

    // Cerrado no cuenta: puede volver a abrir.
    CashRegister::factory()->create(['vendedor_id' => $vendedor->id, 'estado' => 'cerrado']);
    expect(CashRegister::where('vendedor_id', $vendedor->id)->count())->toBe(2);
});

it('una solicitud de nota pendiente no se duplica', function () {
    $documento = ElectronicDocument::factory()->state(['sunat_estado' => 'aceptado'])->create();
    NoteRequest::factory()->create(['electronic_document_id' => $documento->id, 'tipo' => 'nota_credito']);

    expect(fn () => NoteRequest::factory()->create(['electronic_document_id' => $documento->id, 'tipo' => 'nota_credito']))
        ->toThrow(QueryException::class);

    // Una nota de débito sobre el mismo documento es otra solicitud.
    NoteRequest::factory()->create(['electronic_document_id' => $documento->id, 'tipo' => 'nota_debito']);

    // Y si la de crédito se aprueba o se rechaza, se puede pedir otra.
    NoteRequest::query()->where('tipo', 'nota_credito')->update(['estado' => 'rechazada']);
    NoteRequest::factory()->create(['electronic_document_id' => $documento->id, 'tipo' => 'nota_credito']);
    expect(NoteRequest::where('electronic_document_id', $documento->id)->count())->toBe(3);
});

it('los estados de lista cerrada se validan en la base', function () {
    if (DB::getDriverName() !== 'mysql') {
        $this->markTestSkipped('SQLite omite CHECK agregados a tablas existentes porque no existe un ALTER portable.');
    }

    $sede = Sede::factory()->almacen()->create();
    $user = User::factory()->create();

    expect(fn () => InventoryTransfer::create([
        'origen_sede_id' => $sede->id,
        'destino_sede_id' => Sede::factory()->almacen()->create()->id,
        'user_id' => $user->id,
        'estado' => 'inventado',
    ]))->toThrow(QueryException::class);

    expect(fn () => SaleItem::factory()->create(['tipo_afectacion_igv' => '99']))->toThrow(QueryException::class);

    expect(fn () => DocumentSeries::create(['tipo_comprobante' => 'loquesea', 'serie' => 'ZZ99', 'correlativo_actual' => 1]))
        ->toThrow(QueryException::class);

    // Las familias dinámicas de la numeración interna siguen entrando.
    DocumentSeries::create(['tipo_comprobante' => 'baja_20261007', 'serie' => 'RA', 'correlativo_actual' => 1]);
    expect(DocumentSeries::where('tipo_comprobante', 'baja_20261007')->exists())->toBeTrue();
});

it('una cuota parcial vencida pasa a vencido', function () {
    $sale = Sale::factory()->create();
    $cuota = Installment::factory()->create([
        'sale_id' => $sale->id,
        'monto' => 300,
        'estado' => 'parcial',
        'fecha_vencimiento' => now()->subDays(3)->toDateString(),
    ]);
    SalePayment::factory()->create(['sale_id' => $sale->id, 'installment_id' => $cuota->id, 'monto' => 100]);

    Installment::marcarVencidas();
    expect($cuota->refresh()->estado)->toBe('vencido');

    $cuota->recalcularEstado();
    expect($cuota->refresh()->estado)->toBe('vencido');
});

it('quedaron los indices que faltaban y sin el redundante de auditoria', function () {
    $indices = fn (string $tabla): array => collect(DB::select("SHOW INDEX FROM {$tabla}"))
        ->map(fn (object $fila) => $fila->Key_name)
        ->unique()
        ->values()
        ->all();

    expect($indices('electronic_documents'))->toContain('electronic_documents_sunat_estado_index')
        ->and($indices('installments'))->toContain('installments_estado_index')
        ->and($indices('installments'))->toContain('installments_fecha_vencimiento_index')
        ->and($indices('inventory_units'))->toContain('inventory_units_estado_index')
        ->and($indices('sales'))->toContain('sales_fecha_index')
        ->and($indices('certificates'))->toContain('certificates_estado_vigencia_index')
        ->and($indices('equipment'))->toContain('equipment_alertas_index')
        ->and($indices('cash_registers'))->toContain('cash_registers_turno_index')
        ->and($indices('inventory_movements'))->toContain('inventory_movements_producto_sede_index')
        ->and($indices('audit_logs'))->toContain('audit_logs_auditable_index')
        ->and($indices('audit_logs'))->not->toContain('audit_logs_auditable_type_index')
        ->and($indices('audit_logs'))->not->toContain('audit_logs_auditable_id_index');
});
