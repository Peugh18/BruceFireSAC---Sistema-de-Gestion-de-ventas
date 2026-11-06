<?php

use App\Actions\Certificates\EmitirCertificadosDeVenta;
use App\Actions\TecnicoPlanta\ExecuteAndCloseServiceOrder;
use App\Models\Certificate;
use App\Models\CertificateType;
use App\Models\Equipment;
use App\Models\Quote;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Sede;
use App\Models\Service;
use App\Models\ServiceOrder;
use App\Models\User;
use Database\Seeders\CertificateTypeSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->seed([RolesAndPermissionsSeeder::class, CertificateTypeSeeder::class]);
    $this->travelTo('2026-10-23 12:00:00');
});

/** @return array{User, Sale, ServiceOrder, Equipment} */
function recargaTerminada(string $destino, bool $desdeCotizacion = false): array
{
    $user = User::factory()->create();
    $user->assignRole('Vendedor');
    $sale = Sale::factory()->create(['vendedor_id' => $user->id, 'estado' => 'confirmada', 'destino' => $destino]);
    $service = Service::factory()->create(['nombre' => 'RECARGA DE EXTINTOR PQS', 'certificate_type_id' => CertificateType::where('codigo', 'operatividad_garantia')->sole()->id]);
    SaleItem::factory()->forService($service)->create(['sale_id' => $sale->id, 'cantidad' => 1]);
    $order = ServiceOrder::factory()->create(['sale_id' => $sale->id, 'sede_id' => $sale->sede_id, 'client_id' => $sale->client_id, 'service_id' => $service->id, 'estado' => 'datos_completos']);
    if ($desdeCotizacion) {
        $quote = Quote::factory()->create(['client_id' => $sale->client_id, 'destino' => $destino, 'referencia' => 'PLACA: ABC-123']);
        $order->update(['sale_id' => null, 'quote_id' => $quote->id]);
    }
    $equipment = Equipment::factory()->create(['client_id' => $sale->client_id, 'estado' => 'en_servicio', 'tipo_agente' => 'PQS', 'serie_fabricante' => 'CIL-123']);
    $order->equipments()->attach($equipment, ['recibido' => true]);
    app(ExecuteAndCloseServiceOrder::class)->advanceState($order, 'listo_certificado', $user);

    return [$user, $sale, $order, $equipment];
}

test('el taller deja la capacitacion emitida y la PH pendiente, sin duplicar al completar la venta', function (string $destino, string $pendiente) {
    $diaDeLaRecarga = today()->toDateString();
    [$user, $sale, $order, $equipment] = recargaTerminada($destino);
    $original = Certificate::where('service_order_id', $order->id)->whereHas('certificateType', fn ($q) => $q->where('codigo', 'operatividad_garantia'))->sole();
    expect($original->datos['tipos'] ?? null)->toBe(EmitirCertificadosDeVenta::tiposPorDestino($destino));
    expect(Certificate::count())->toBe($destino === 'local_cliente' ? 2 : 1);

    $this->actingAs($user)->get(route('vendedor.ventas.certificados.create', ['current_team' => $user->currentTeam, 'sale' => $sale]))
        ->assertOk()->assertInertia(fn ($page) => $page->has('unidades', 1)->where('unidades.0.equipment_id', $equipment->id)->where('gruposActuales.0.tipos.1', $pendiente));

    $this->travelTo('2026-11-02 12:00:00');
    $unidad = ['equipment_id' => $equipment->id];
    if ($pendiente === 'prueba_hidrostatica') {
        $unidad += ['fecha_ultima_ph' => '2026-10-22', 'presion_ph' => '600 PSI', 'tiempo_ph' => '60 SEG', 'resultado_ph' => 'aprobado'];
    }
    $emitidos = app(EmitirCertificadosDeVenta::class)->handle($sale, [['tipos' => EmitirCertificadosDeVenta::tiposPorDestino($destino), 'unidades' => [$unidad], 'capacitacion' => ['curso' => 'USO DE EXTINTORES', 'horas' => 2, 'instructor' => 'Instructor registrado']]]);
    expect($emitidos)->toHaveCount(2)
        ->and(Certificate::count())->toBe(2)
        ->and($original->fresh()->service_order_id)->toBe($order->id)
        ->and($original->fresh()->tipo_atencion)->toBe('recarga')
        ->and($original->fresh()->certificateUnits->sole()->fecha_ultima_recarga->toDateString())->toBe($diaDeLaRecarga);
})->with(['local' => ['local_cliente', 'capacitacion'], 'vehiculo' => ['vehiculo', 'prueba_hidrostatica']]);

test('rechazar un equipo ajeno no modifica su serie ni los equipos validos', function () {
    [$user, $sale, $order, $equipment] = recargaTerminada('local_cliente');
    $ajeno = Equipment::factory()->create(['serie_fabricante' => 'ORIGINAL']);
    $this->actingAs($user)->post(route('vendedor.ventas.certificados.store', ['current_team' => $user->currentTeam, 'sale' => $sale]), ['grupos' => [[
        'tipos' => ['operatividad_garantia'], 'unidades' => [
            ['equipment_id' => $equipment->id, 'serie_fabricante' => 'CAMBIO'],
            ['equipment_id' => $ajeno->id, 'serie_fabricante' => 'AJENO'],
        ],
    ]]])->assertSessionHasErrors('grupos.0.unidades');
    expect($ajeno->fresh()->serie_fabricante)->toBe('ORIGINAL')
        ->and($equipment->fresh()->serie_fabricante)->toBe('CIL-123');
});

test('una recarga vehicular cotizada sin cobrar espera al cobro y conserva placa y ph pendiente', function () {
    [$user, $sale, $order, $equipment] = recargaTerminada('vehiculo', true);
    // Sin comprobante no hay certificado; sale cuando se cobra la orden.
    expect(Certificate::where('service_order_id', $order->id)->count())->toBe(0);
    $order->update(['sale_id' => $sale->id]);
    app(ExecuteAndCloseServiceOrder::class)->emitirCertificadosPendientes($order->fresh());
    $certificado = Certificate::where('service_order_id', $order->id)->sole();
    expect($certificado->datos['destino'])->toBe('vehiculo')
        ->and($certificado->datos['tipos'])->toBe(['operatividad_garantia', 'prueba_hidrostatica'])
        ->and($certificado->referencia)->toBe('PLACA: ABC-123');
});

test('en una venta mixta el extintor nuevo se suma al certificado de la recarga, con el mismo numero', function () {
    [$user, $sale, $order, $equipment] = recargaTerminada('local_cliente');
    $original = Certificate::where('service_order_id', $order->id)->whereHas('certificateType', fn ($q) => $q->where('codigo', 'operatividad_garantia'))->sole();
    $nuevo = Equipment::factory()->create(['client_id' => $sale->client_id, 'tipo_agente' => 'PQS']);
    SaleItem::factory()->create(['sale_id' => $sale->id, 'equipment_id' => $nuevo->id, 'tipo_linea' => 'unidad_nueva']);
    app(EmitirCertificadosDeVenta::class)->automaticos($sale->fresh());
    // Un certificado por local: la recarga y el nuevo en el mismo, que conserva su número.
    expect($original->fresh()->certificateUnits->pluck('equipment_id')->sort()->values()->all())->toBe(collect([$equipment->id, $nuevo->id])->sort()->values()->all())
        ->and($original->fresh()->estado)->toBe('vigente')
        ->and($original->fresh()->numero)->toBe($original->numero)
        ->and(Certificate::where('estado', 'vigente')->count())->toBe(2);
});

test('la venta no certifica equipos de taller pendientes no recibidos o de otra sede', function (string $caso) {
    [$user, $sale, $order, $equipment] = recargaTerminada('local_cliente');
    $sale->items()->first()->update(['equipment_id' => $equipment->id]);
    if ($caso === 'pendiente') {
        $order->update(['estado' => 'en_proceso']);
    } elseif ($caso === 'no recibido') {
        $order->equipments()->updateExistingPivot($equipment->id, ['recibido' => false]);
    } elseif ($caso === 'otra sede') {
        $order->update(['sede_id' => Sede::factory()->create()->id]);
    } else {
        $equipment->update(['estado' => 'baja']);
    }
    expect(fn () => app(EmitirCertificadosDeVenta::class)->handle($sale, [[
        'tipos' => ['operatividad_garantia'], 'unidades' => [['equipment_id' => $equipment->id]],
    ]]))->toThrow(ValidationException::class);
})->with(['pendiente', 'no recibido', 'otra sede', 'baja']);

test('vincular despues la orden a la venta permite corregir su certificado original', function () {
    [$user, $sale, $order, $equipment] = recargaTerminada('local_cliente');
    $original = Certificate::where('service_order_id', $order->id)->whereHas('certificateType', fn ($q) => $q->where('codigo', 'operatividad_garantia'))->sole();
    $original->update(['sale_id' => null, 'datos' => null]);
    $this->actingAs($user)->get(route('vendedor.ventas.certificados.create', ['current_team' => $user->currentTeam, 'sale' => $sale]))
        ->assertInertia(fn ($page) => $page->where('gruposActuales.0.tipos', ['operatividad_garantia', 'capacitacion']));
    $emitidos = app(EmitirCertificadosDeVenta::class)->handle($sale, [[
        'tipos' => ['operatividad_garantia'], 'unidades' => [['equipment_id' => $equipment->id, 'numero_cliente' => '03']],
    ]]);
    expect($emitidos->sole()->id)->toBe($original->id)->and(Certificate::count())->toBe(2);
});

test('una recarga para local deja la capacitacion con los datos de la empresa y no se duplica al cobrar', function () {
    [, $sale, $order] = recargaTerminada('local_cliente');

    $capacitacion = Certificate::where('service_order_id', $order->id)->whereHas('certificateType', fn ($q) => $q->where('codigo', 'capacitacion'))->sole();
    expect($capacitacion->datos['curso'])->toBe('USO Y MANEJO DE EXTINTORES')
        ->and($capacitacion->datos['horas'])->toBe(4)
        ->and($capacitacion->estado)->toBe('vigente');

    app(EmitirCertificadosDeVenta::class)->automaticos($sale->fresh());

    expect(Certificate::whereHas('certificateType', fn ($q) => $q->where('codigo', 'capacitacion'))->count())->toBe(1);
});

test('una recarga para vehiculo no emite capacitacion', function () {
    [, , $order] = recargaTerminada('vehiculo');

    expect(Certificate::where('service_order_id', $order->id)->whereHas('certificateType', fn ($q) => $q->where('codigo', 'capacitacion'))->exists())->toBeFalse();
});
