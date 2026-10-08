<?php

use App\Actions\Billing\AnularVentaPorEnviar;
use App\Actions\Billing\AplicarNotaAlSaldo;
use App\Actions\Billing\EmitElectronicDocument;
use App\Actions\Billing\IssueDebitNote;
use App\Actions\Cash\CloseCashRegister;
use App\Actions\Certificates\IssueCertificate;
use App\Actions\Sales\CreateSale;
use App\Actions\Sales\RevertSale;
use App\Actions\Tecnico\ProcessChecklist;
use App\Actions\TecnicoCampo\RegisterCollection;
use App\Enums\TeamRole;
use App\Models\CashRegister;
use App\Models\Certificate;
use App\Models\CertificateType;
use App\Models\Client;
use App\Models\Deficiency;
use App\Models\DeficiencyAuthorization;
use App\Models\ElectronicDocument;
use App\Models\Equipment;
use App\Models\Installment;
use App\Models\InventoryUnit;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SalePayment;
use App\Models\SaleRefund;
use App\Models\Sede;
use App\Models\ServiceOrder;
use App\Models\ServiceOrderEvent;
use App\Models\Team;
use App\Models\TechnicalChecklist;
use App\Models\User;
use Database\Seeders\CertificateTypeSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\Events\TransactionBeginning;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;

/**
 * Simula la petición "competidora" que gana la carrera: ejecuta $accion en el
 * momento en que se abre la primera transacción del flujo bajo prueba (es
 * decir, ya pasadas las validaciones que se hacen FUERA de la transacción).
 */
function alComenzarLaTransaccion(callable $accion): void
{
    $disparado = false;

    Event::listen(TransactionBeginning::class, function () use ($accion, &$disparado): void {
        if ($disparado) {
            return;
        }

        $disparado = true;
        $accion();
    });
}

/**
 * Venta al contado (nota de venta) confirmada y cobrada en el turno abierto
 * del vendedor, con su unidad de inventario para poder editarla.
 *
 * @return array{0: Sale, 1: Product, 2: InventoryUnit}
 */
function ventaContadoCobradaParaConcurrencia(User $vendedor, float $precio = 100.0): array
{
    CashRegister::factory()->create(['vendedor_id' => $vendedor->id]);

    $sede = Sede::factory()->almacen()->create();
    $product = Product::factory()->create();
    $unit = InventoryUnit::factory()->create([
        'product_id' => $product->id,
        'sede_almacen_id' => $sede->id,
        'estado' => 'disponible',
    ]);

    $sale = app(CreateSale::class)->handle([
        'client_id' => Client::factory()->create()->id,
        'sede_id' => $sede->id,
        'fecha' => now()->toDateString(),
        'destino' => 'local_cliente',
        'condicion_pago' => 'contado',
        'medio_pago' => 'efectivo',
        'comprobante_tipo' => 'nota_venta',
    ], [[
        'tipo_linea' => 'unidad_nueva',
        'numero_serie' => $unit->numero_serie,
        'product_id' => $product->id,
        'cantidad' => 1,
        'precio_unitario' => $precio,
    ]], $vendedor->id);

    $sale->update(['estado' => 'confirmada']);
    SalePayment::factory()->create([
        'sale_id' => $sale->id,
        'forma_pago' => 'efectivo',
        'monto' => $precio,
    ]);

    return [$sale->refresh(), $product, $unit];
}

beforeEach(function () {
    $this->seed([RolesAndPermissionsSeeder::class, CertificateTypeSeeder::class]);
});

/*
|--------------------------------------------------------------------------
| A1. Doble autorización de deficiencia → doble cuota por cobrar (DINERO)
|--------------------------------------------------------------------------
*/

test('A1: dos autorizaciones simultaneas de una deficiencia no crean dos autorizaciones ni dos cuotas', function () {
    $user = vendedorUser();
    $sale = Sale::factory()->create(['vendedor_id' => $user->id, 'estado' => 'confirmada', 'condicion_pago' => 'contado']);
    $order = ServiceOrder::factory()->create(['client_id' => $sale->client_id, 'sale_id' => $sale->id]);
    $deficiency = Deficiency::factory()->create(['service_order_id' => $order->id, 'estado' => 'esperando_autorizacion']);

    // La otra petición gana la carrera y ya autorizó la deficiencia mientras
    // esta se decidía: la transacción propia debe rechazarla.
    alComenzarLaTransaccion(fn () => DB::table('deficiencies')->where('id', $deficiency->id)->update(['estado' => 'autorizada']));

    $this->actingAs($user)
        ->post(route('vendedor.deficiencias.autorizar', ['current_team' => $user->currentTeam, 'deficiency' => $deficiency]), [
            'autorizado' => true,
            'autorizado_por' => 'Ana Cliente',
            'canal' => 'whatsapp',
            'fecha' => today()->toDateString(),
            'importe' => 45.5,
        ])
        ->assertSessionHasErrors('deficiency');

    expect(DeficiencyAuthorization::where('deficiency_id', $deficiency->id)->count())->toBe(0)
        ->and($sale->installments()->count())->toBe(0);
});

test('A1: un doble clic en autorizar crea una sola autorizacion y una sola cuota por cobrar', function () {
    $user = vendedorUser();
    $sale = Sale::factory()->create(['vendedor_id' => $user->id, 'estado' => 'confirmada', 'condicion_pago' => 'contado']);
    $order = ServiceOrder::factory()->create(['client_id' => $sale->client_id, 'sale_id' => $sale->id]);
    $deficiency = Deficiency::factory()->create(['service_order_id' => $order->id, 'estado' => 'esperando_autorizacion']);

    $datos = [
        'autorizado' => true,
        'autorizado_por' => 'Ana Cliente',
        'canal' => 'whatsapp',
        'fecha' => today()->toDateString(),
        'importe' => 45.5,
    ];

    $this->actingAs($user)
        ->post(route('vendedor.deficiencias.autorizar', ['current_team' => $user->currentTeam, 'deficiency' => $deficiency]), $datos)
        ->assertSessionHasNoErrors();

    $this->actingAs($user)
        ->post(route('vendedor.deficiencias.autorizar', ['current_team' => $user->currentTeam, 'deficiency' => $deficiency]), $datos)
        ->assertSessionHasErrors('deficiency');

    expect(DeficiencyAuthorization::where('deficiency_id', $deficiency->id)->count())->toBe(1)
        ->and($sale->installments()->count())->toBe(1)
        ->and((float) $sale->installments()->first()->monto)->toBe(45.5);
});

/*
|--------------------------------------------------------------------------
| A2. Doble finalización de inspección/mantenimiento → doble certificado
|--------------------------------------------------------------------------
*/

test('A2: IssueCertificate no emite dos certificados del mismo tipo para la misma orden', function () {
    $client = Client::factory()->create();
    $order = ServiceOrder::factory()->create(['client_id' => $client->id]);
    $equipo = Equipment::factory()->create(['client_id' => $client->id]);
    $tipo = CertificateType::query()->where('codigo', 'operatividad_garantia')->firstOrFail();
    $unidades = [['equipment_id' => $equipo->id, 'numero_serie' => $equipo->numero_serie]];

    app(IssueCertificate::class)->handle($tipo, $client, $unidades, null, $order->id);

    expect(fn () => app(IssueCertificate::class)->handle($tipo, $client, $unidades, null, $order->id))
        ->toThrow(ValidationException::class);

    expect(Certificate::where('service_order_id', $order->id)->count())->toBe(1);
});

test('A2: dos finalizaciones simultaneas de la inspeccion no emiten dos certificados', function () {
    $team = Team::factory()->create();
    $user = User::factory()->create(['current_team_id' => $team->id]);
    $team->members()->attach($user, ['role' => TeamRole::Admin->value]);
    $user->assignRole('TecnicoCampo');

    $order = ServiceOrder::factory()->create(['departamento_tecnico' => 'campo', 'estado' => 'recibido_planta']);
    $equipo = Equipment::factory()->create(['client_id' => $order->client_id]);
    $order->equipments()->attach($equipo->id);
    TechnicalChecklist::create([
        'service_order_id' => $order->id,
        'equipment_id' => $equipo->id,
        'user_id' => $user->id,
        'origen' => 'campo',
        'tipo_equipo' => 'pqs',
        'items' => [],
        'resultado_general' => 'conforme',
    ]);

    // La otra petición ya finalizó la inspección mientras esta se decidía.
    alComenzarLaTransaccion(fn () => DB::table('service_orders')->where('id', $order->id)->update(['estado' => 'listo_entrega']));

    $this->actingAs($user)
        ->post(route('tecnico-campo.inspecciones.complete', ['current_team' => $team, 'service_order' => $order]), [
            'conformidad_nombre' => 'Juan Pérez',
            'conformidad_aceptada' => true,
        ])
        ->assertSessionHasErrors('estado');

    expect(Certificate::count())->toBe(0)
        ->and(ServiceOrderEvent::where('service_order_id', $order->id)->where('tipo', 'trabajo_completado')->count())->toBe(0);
});

/*
|--------------------------------------------------------------------------
| M2. NC parcial sobre venta al contado: el dinero a devolver se registra
|--------------------------------------------------------------------------
*/

test('M2: el excedente de una NC parcial sobre una venta al contado queda como devolucion en caja', function () {
    $user = vendedorUser();
    [$sale] = ventaContadoCobradaParaConcurrencia($user);

    $factura = ElectronicDocument::factory()->create([
        'sale_id' => $sale->id,
        'tipo' => 'factura',
        'sunat_estado' => 'aceptado',
    ]);
    $nc = ElectronicDocument::factory()->create([
        'sale_id' => $sale->id,
        'tipo' => 'nota_credito',
        'cpe_afectado_id' => $factura->id,
        'motivo_catalogo' => '04',
        'importe' => 40,
        'sunat_estado' => 'aceptado',
    ]);

    app(AplicarNotaAlSaldo::class)->handle($nc);

    $devolucion = SaleRefund::where('sale_id', $sale->id)->sole();

    expect((float) $devolucion->monto)->toBe(40.0)
        ->and($devolucion->forma_pago)->toBe('efectivo')
        ->and($devolucion->cash_register_id)->toBe(CashRegister::abiertaDe($user->id)?->id);
});

/*
|--------------------------------------------------------------------------
| M3. Carrera al cerrar caja vs cobro simultáneo
|--------------------------------------------------------------------------
*/

test('M3: dos cierres de caja a la vez cierran el turno una sola vez', function () {
    $user = vendedorUser();
    $caja = CashRegister::factory()->create(['vendedor_id' => $user->id, 'monto_apertura' => 100.0]);
    SalePayment::factory()->create([
        'sale_id' => Sale::factory()->create(['vendedor_id' => $user->id])->id,
        'cash_register_id' => $caja->id,
        'forma_pago' => 'efectivo',
        'monto' => 50.0,
    ]);

    // Dos lecturas del mismo turno, antes de que cualquiera cierre.
    $primera = CashRegister::query()->findOrFail($caja->id);
    $segunda = CashRegister::query()->findOrFail($caja->id);

    $cerrada = app(CloseCashRegister::class)->handle($primera, 150.0);

    expect($cerrada->estado)->toBe('cerrado')
        ->and((float) $cerrada->monto_esperado_calculado)->toBe(150.0);

    // El segundo cierre parte de una lectura vieja del turno: ya está cerrado.
    expect(fn () => app(CloseCashRegister::class)->handle($segunda, 150.0))
        ->toThrow(ValidationException::class);
});

/*
|--------------------------------------------------------------------------
| M4. Anulación de venta "por enviar" vs programador de envío
|--------------------------------------------------------------------------
*/

test('M4: no se descarta el comprobante mientras se esta enviando a SUNAT', function () {
    $user = vendedorUser();
    [$sale] = ventaContadoCobradaParaConcurrencia($user);
    $documento = ElectronicDocument::factory()->create([
        'sale_id' => $sale->id,
        'tipo' => 'factura',
        'serie' => 'F001',
        'correlativo' => 42,
        'sunat_estado' => 'por_enviar',
    ]);

    // El envío a SUNAT tiene el bloqueo del comprobante tomado.
    $lock = Cache::lock("sunat-envio-{$documento->id}", 300);
    expect($lock->get())->toBeTrue();

    expect(fn () => app(AnularVentaPorEnviar::class)->handle($sale))
        ->toThrow(ValidationException::class);

    expect(ElectronicDocument::query()->whereKey($documento->id)->exists())->toBeTrue()
        ->and($sale->fresh()->estado)->toBe('confirmada');

    $lock->release();
});

test('M4: un comprobante con intento de envio no se descarta', function () {
    $user = vendedorUser();
    [$sale] = ventaContadoCobradaParaConcurrencia($user);
    $documento = ElectronicDocument::factory()->create([
        'sale_id' => $sale->id,
        'tipo' => 'factura',
        'serie' => 'F001',
        'correlativo' => 43,
        'sunat_estado' => 'por_enviar',
        'intento_envio_at' => now(),
    ]);

    expect(fn () => app(EmitElectronicDocument::class)->descartarPorEnviar($documento))
        ->toThrow(InvalidArgumentException::class);

    expect(ElectronicDocument::query()->whereKey($documento->id)->exists())->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| M5. Los cobros de contado no se borran físicamente al editar una venta
|--------------------------------------------------------------------------
*/

test('M5: editar una venta cobrada conserva el cobro anterior como evidencia', function () {
    $user = vendedorUser();
    [$sale, $product, $unit] = ventaContadoCobradaParaConcurrencia($user);
    $cobroOriginal = SalePayment::where('sale_id', $sale->id)->sole();

    $this->actingAs($user)
        ->put(route('vendedor.ventas.update', ['current_team' => $user->currentTeam, 'sale' => $sale]), [
            'client_id' => $sale->client_id,
            'sede_id' => $sale->sede_id,
            'fecha' => $sale->fecha->toDateString(),
            'destino' => 'local_cliente',
            'condicion_pago' => 'contado',
            'medio_pago' => 'efectivo',
            'comprobante_tipo' => 'nota_venta',
            'items' => [[
                'tipo_linea' => 'unidad_nueva',
                'numero_serie' => $unit->numero_serie,
                'product_id' => $product->id,
                'cantidad' => 1,
                'precio_unitario' => 120,
            ]],
        ])
        ->assertSessionHasNoErrors();

    $pagos = SalePayment::withTrashed()->where('sale_id', $sale->id)->get();

    expect($pagos)->toHaveCount(2)
        ->and(SalePayment::onlyTrashed()->where('sale_id', $sale->id)->count())->toBe(1)
        ->and(SalePayment::where('sale_id', $sale->id)->count())->toBe(1)
        ->and(SalePayment::onlyTrashed()->where('sale_id', $sale->id)->firstOrFail()->id)->toBe($cobroOriginal->id)
        ->and(SalePayment::onlyTrashed()->where('sale_id', $sale->id)->firstOrFail()->anulado_motivo)->toBe('Reemplazado por la edición de la venta');
});

/*
|--------------------------------------------------------------------------
| M6. Escrituras multi-tabla sin transacción en flujos de campo y entrega
|--------------------------------------------------------------------------
*/

test('M6: un doble POST de recojo no duplica el eslabon de custodia', function () {
    $user = User::factory()->create();
    $order = ServiceOrder::factory()->create(['estado' => 'pendiente_recepcion']);
    $equipo = Equipment::factory()->create(['client_id' => $order->client_id]);
    $order->equipments()->attach($equipo->id);

    // La otra petición ya registró su eslabón mientras esta se decidía.
    alComenzarLaTransaccion(function () use ($order, $user): void {
        ServiceOrderEvent::create([
            'service_order_id' => $order->id,
            'tipo' => 'otro',
            'user_id' => $user->id,
            'payload' => ['eslabon_custodia' => 'recojo_campo'],
        ]);
    });

    expect(fn () => app(RegisterCollection::class)->execute($order, $user, [
        'cantidad' => 1,
        'contacto_nombre' => 'Ana',
        'conformidad_cliente' => true,
    ]))->toThrow(InvalidArgumentException::class, 'Este recojo ya se registró.');

    expect(ServiceOrderEvent::where('service_order_id', $order->id)->where('payload->eslabon_custodia', 'recojo_campo')->count())->toBe(0);
});

test('M6: un doble POST de entrega en mostrador no crea dos eventos de entrega', function () {
    $user = vendedorUser();
    $order = ServiceOrder::factory()->create(['sede_id' => $user->sede_id, 'estado' => 'listo_entrega']);

    // La otra petición ya registró la entrega mientras esta se decidía.
    alComenzarLaTransaccion(function () use ($order, $user): void {
        ServiceOrderEvent::create([
            'service_order_id' => $order->id,
            'tipo' => 'entrega_registrada',
            'user_id' => $user->id,
            'payload' => ['accion' => 'entrega_final_realizada', 'conformidad_aceptada' => true],
        ]);
    });

    $this->actingAs($user)
        ->post(route('vendedor.ordenes-servicio.entrega-mostrador.store', [
            'current_team' => $user->currentTeam,
            'service_order' => $order,
        ]), [
            'receptor_nombre' => 'Cliente',
            'receptor_dni' => '12345678',
            'conformidad_aceptada' => true,
        ])
        ->assertStatus(422);

    expect(ServiceOrderEvent::where('service_order_id', $order->id)->where('payload->accion', 'entrega_final_realizada')->count())->toBe(0);
});

test('M6: no se crea nota de debito sobre una venta que se anulo en la carrera', function () {
    [$sale, $factura] = ventaConFactura();

    // La otra petición anula la venta mientras esta emite la nota.
    alComenzarLaTransaccion(fn () => DB::table('sales')->where('id', $sale->id)->update(['estado' => 'anulada']));

    expect(fn () => app(IssueDebitNote::class)->handle($factura, '03', 'Interés moratorio', 10.0))
        ->toThrow(ValidationException::class);

    expect(ElectronicDocument::where('sale_id', $sale->id)->where('tipo', 'nota_debito')->count())->toBe(0);
});

/*
|--------------------------------------------------------------------------
| M7. ProcessChecklist "rebobina" la orden y dos checklists se pisan
|--------------------------------------------------------------------------
*/

test('M7: un checklist conforme no rebobina una orden que ya quedo esperando autorizacion', function () {
    $user = User::factory()->create();
    $order = ServiceOrder::factory()->create(['estado' => 'recibido_planta']);
    $equipo = Equipment::factory()->create(['client_id' => $order->client_id]);
    $order->equipments()->attach($equipo->id);

    // La orden se cargó en recibido_planta, pero otro checklist concurrente ya la
    // dejó esperando autorización antes de que esta transacción escribiera.
    $obsoleto = ServiceOrder::query()->findOrFail($order->id);
    DB::table('service_orders')->where('id', $order->id)->update(['estado' => 'esperando_autorizacion']);

    app(ProcessChecklist::class)->execute($obsoleto, $equipo, $user, [
        'origen' => 'campo',
        'items' => ['manometro' => ['estado' => 'conforme']],
    ]);

    expect($order->fresh()->estado)->toBe('esperando_autorizacion');
});

/*
|--------------------------------------------------------------------------
| B1. Una cuota `parcial` no queda huérfana sobre una venta anulada
|--------------------------------------------------------------------------
*/

test('B1: al anular la venta tambien se cierra la cuota parcial', function () {
    $user = vendedorUser();
    [$sale] = ventaContadoCobradaParaConcurrencia($user);

    $cuota = Installment::factory()->create([
        'sale_id' => $sale->id,
        'numero_cuota' => 1,
        'monto' => 300,
        'monto_acreditado' => 0,
        'estado' => 'parcial',
    ]);
    $pagoCuota = SalePayment::factory()->create([
        'sale_id' => $sale->id,
        'installment_id' => $cuota->id,
        'forma_pago' => 'efectivo',
        'monto' => 100,
    ]);

    app(RevertSale::class)->handle($sale);

    expect($sale->fresh()->estado)->toBe('anulada')
        ->and(Installment::where('sale_id', $sale->id)->count())->toBe(0)
        ->and(SalePayment::withTrashed()->whereKey($pagoCuota->id)->exists())->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| B3. La fecha se valida también al actualizar
|--------------------------------------------------------------------------
*/

test('B3: al actualizar una venta la fecha futura se rechaza', function () {
    $user = vendedorUser();
    [$sale, $product, $unit] = ventaContadoCobradaParaConcurrencia($user, 100.0);
    $sale->update(['estado' => 'borrador']);

    $this->actingAs($user)
        ->put(route('vendedor.ventas.update', ['current_team' => $user->currentTeam, 'sale' => $sale]), [
            'client_id' => $sale->client_id,
            'sede_id' => $sale->sede_id,
            'fecha' => today()->addDay()->toDateString(),
            'destino' => 'local_cliente',
            'condicion_pago' => 'contado',
            'medio_pago' => 'efectivo',
            'comprobante_tipo' => 'nota_venta',
            'items' => [[
                'tipo_linea' => 'unidad_nueva',
                'numero_serie' => $unit->numero_serie,
                'product_id' => $product->id,
                'cantidad' => 1,
                'precio_unitario' => 100,
            ]],
        ])
        ->assertSessionHasErrors('fecha');

    expect($sale->fresh()->fecha->toDateString())->not->toBe(today()->addDay()->toDateString());
});

test('B3: una venta vieja se sigue editando con la fecha que ya tenia', function () {
    $user = vendedorUser();
    [$sale, $product, $unit] = ventaContadoCobradaParaConcurrencia($user, 100.0);
    $sale->update(['estado' => 'borrador', 'fecha' => today()->subDays(10)]);

    $this->actingAs($user)
        ->put(route('vendedor.ventas.update', ['current_team' => $user->currentTeam, 'sale' => $sale]), [
            'client_id' => $sale->client_id,
            'sede_id' => $sale->sede_id,
            'fecha' => today()->subDays(10)->toDateString(),
            'destino' => 'local_cliente',
            'condicion_pago' => 'contado',
            'medio_pago' => 'efectivo',
            'comprobante_tipo' => 'nota_venta',
            'items' => [[
                'tipo_linea' => 'unidad_nueva',
                'numero_serie' => $unit->numero_serie,
                'product_id' => $product->id,
                'cantidad' => 1,
                'precio_unitario' => 100,
            ]],
        ])
        ->assertSessionHasNoErrors();

    expect($sale->fresh()->fecha->toDateString())->toBe(today()->subDays(10)->toDateString());
});

/*
|--------------------------------------------------------------------------
| B4. Detectar el extintor "descargado" no depende de una sola palabra
|--------------------------------------------------------------------------
*/

test('B4: "sin carga" marca el extintor como descargado y el control explicito tambien', function () {
    $user = User::factory()->create();

    $ordenTexto = ServiceOrder::factory()->create(['estado' => 'recibido_planta']);
    $equipoTexto = Equipment::factory()->create(['client_id' => $ordenTexto->client_id, 'estado' => 'operativo']);
    $ordenTexto->equipments()->attach($equipoTexto->id);

    app(ProcessChecklist::class)->execute($ordenTexto, $equipoTexto, $user, [
        'origen' => 'campo',
        'items' => ['agente_carga' => ['estado' => 'observado', 'condicion' => 'Extintor sin carga']],
    ]);

    $ordenControl = ServiceOrder::factory()->create(['estado' => 'recibido_planta']);
    $equipoControl = Equipment::factory()->create(['client_id' => $ordenControl->client_id, 'estado' => 'operativo']);
    $ordenControl->equipments()->attach($equipoControl->id);

    app(ProcessChecklist::class)->execute($ordenControl, $equipoControl, $user, [
        'origen' => 'campo',
        'equipo_descargado' => true,
        'items' => ['cilindro' => ['estado' => 'observado', 'condicion' => 'Rayón en la base']],
    ]);

    expect($equipoTexto->fresh()->estado)->toBe('descargado')
        ->and($equipoControl->fresh()->estado)->toBe('descargado');
});

/*
|--------------------------------------------------------------------------
| B5. N+1 al filtrar los equipos conformes del certificado
|--------------------------------------------------------------------------
*/

test('B5: los equipos conformes del certificado se resuelven en una sola consulta', function () {
    $team = Team::factory()->create();
    $user = User::factory()->create(['current_team_id' => $team->id]);
    $team->members()->attach($user, ['role' => TeamRole::Admin->value]);
    $user->assignRole('TecnicoCampo');

    $order = ServiceOrder::factory()->create(['departamento_tecnico' => 'campo', 'estado' => 'recibido_planta']);

    foreach (range(1, 3) as $i) {
        $equipo = Equipment::factory()->create(['client_id' => $order->client_id]);
        $order->equipments()->attach($equipo->id);
        TechnicalChecklist::create([
            'service_order_id' => $order->id,
            'equipment_id' => $equipo->id,
            'user_id' => $user->id,
            'origen' => 'campo',
            'tipo_equipo' => 'pqs',
            'items' => [],
            'resultado_general' => 'conforme',
        ]);
    }

    $consultas = 0;
    DB::listen(function ($query) use (&$consultas): void {
        if (str_contains($query->sql, 'technical_checklists')) {
            $consultas++;
        }
    });

    $this->actingAs($user)
        ->post(route('tecnico-campo.inspecciones.complete', ['current_team' => $team, 'service_order' => $order]), [
            'conformidad_nombre' => 'Juan Pérez',
            'conformidad_aceptada' => true,
        ])
        ->assertSessionHasNoErrors();

    expect($consultas)->toBe(1)
        ->and(Certificate::count())->toBe(1);
});
