<?php

use App\Actions\Sales\ProcessSaleItem;
use App\Models\Certificate;
use App\Models\CertificateUnit;
use App\Models\Client;
use App\Models\Equipment;
use App\Models\Installment;
use App\Models\InventoryUnit;
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
