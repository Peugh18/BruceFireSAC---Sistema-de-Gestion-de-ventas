<?php

use App\Actions\Certificates\IssueCertificate;
use App\Actions\Sales\EditarVentaEmitida;
use App\Models\CertificateType;
use App\Models\Client;
use App\Models\Equipment;
use App\Models\ProductCategory;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Service;
use App\Models\ServiceOrder;
use App\Services\Certificates\TipoAtencionExtintor;

test('la atencion de la venta corresponde al servicio real y no al nombre generico de la linea', function (string $nombre, ?string $categoria, string $esperado) {
    $sale = Sale::factory()->create();
    $equipo = Equipment::factory()->create(['client_id' => $sale->client_id]);
    if ($categoria !== null) {
        ProductCategory::factory()->create(['clave' => $categoria]);
    }
    $service = Service::factory()->create(['nombre' => $nombre, 'categoria' => $categoria]);
    SaleItem::factory()->forService($service)->create(['sale_id' => $sale->id, 'equipment_id' => $equipo->id]);

    $atencion = TipoAtencionExtintor::deVenta($sale, [$equipo->id]);

    expect($atencion)->toBe($esperado);
})->with([
    'recarga por nombre' => ['RECARGA PQS 6 KG', null, 'recarga'],
    'recarga por categoria' => ['Servicio PQS 6 KG', 'recarga', 'recarga'],
    'recarga combinada con mantenimiento' => ['Recarga y mantenimiento CO2', null, 'recarga'],
    'categoria combinada' => ['Servicio PQS', 'recarga_mantenimiento', 'recarga'],
    'mantenimiento por nombre' => ['MANTENIMIENTO PQS 6 KG', null, 'mantenimiento'],
    'mantenimiento por categoria' => ['Servicio PQS 6 KG', 'mantenimiento', 'mantenimiento'],
    'prueba hidrostatica no equivale a recarga' => ['Prueba hidrostática extintor', null, ''],
    'servicio arbitrario no equivale a recarga' => ['Inspección técnica de seguridad', null, ''],
]);

test('la venta identifica unidades nuevas y conserva cada atencion del grupo seleccionado', function () {
    $sale = Sale::factory()->create();
    [$nuevo, $recargado, $mantenido] = Equipment::factory()->count(3)->create(['client_id' => $sale->client_id]);
    SaleItem::factory()->create(['sale_id' => $sale->id, 'equipment_id' => $nuevo->id]);
    $recarga = Service::factory()->create(['nombre' => 'Recarga PQS 6 KG']);
    $mantenimiento = Service::factory()->create(['nombre' => 'Mantenimiento PQS 6 KG']);
    SaleItem::factory()->forService($mantenimiento)->create(['sale_id' => $sale->id, 'equipment_id' => $mantenido->id]);
    SaleItem::factory()->forService($recarga)->create(['sale_id' => $sale->id, 'equipment_id' => $recargado->id]);
    SaleItem::factory()->forService($recarga)->create(['sale_id' => $sale->id, 'equipment_id' => $recargado->id]);

    expect(TipoAtencionExtintor::deVenta($sale, [$nuevo->id]))->toBe('nuevo');
    expect(TipoAtencionExtintor::deVenta($sale, [$mantenido->id, $recargado->id]))->toBe('recarga / mantenimiento');
    expect(TipoAtencionExtintor::deVenta($sale, [$nuevo->id, $mantenido->id, $recargado->id]))->toBe('nuevo / recarga / mantenimiento');
    expect(TipoAtencionExtintor::deVenta($sale, []))->toBe('');
});

test('el certificado de orden toma su atencion del servicio realizado', function (string $nombre, string $esperado) {
    $tipo = CertificateType::factory()->create(['codigo' => 'operatividad_garantia']);
    $service = Service::factory()->create(['nombre' => $nombre]);
    $orden = ServiceOrder::factory()->create(['service_id' => $service->id]);
    $equipo = Equipment::factory()->create(['client_id' => $orden->client_id]);

    $certificate = app(IssueCertificate::class)->handle($tipo, $orden->client, [['equipment_id' => $equipo->id]], serviceOrderId: $orden->id);

    $this->assertDatabaseHas('certificates', ['id' => $certificate->id, 'tipo_atencion' => $esperado]);
})->with([
    'recarga' => ['Recarga y mantenimiento PQS 6 KG', 'recarga'],
    'solo mantenimiento' => ['Mantenimiento PQS 6 KG', 'mantenimiento'],
    'sin evidencia de recarga' => ['Prueba hidrostática extintor', ''],
]);

test('la atencion explicita prevalece sobre la deduccion de la orden', function () {
    $tipo = CertificateType::factory()->create(['codigo' => 'operatividad_garantia']);
    $service = Service::factory()->create(['nombre' => 'Recarga PQS 6 KG']);
    $orden = ServiceOrder::factory()->create(['service_id' => $service->id]);
    $equipo = Equipment::factory()->create(['client_id' => $orden->client_id]);

    $certificate = app(IssueCertificate::class)->handle($tipo, $orden->client, [['equipment_id' => $equipo->id]], serviceOrderId: $orden->id, extra: ['tipo_atencion' => 'mantenimiento']);

    $this->assertDatabaseHas('certificates', ['id' => $certificate->id, 'tipo_atencion' => 'mantenimiento']);
});

test('la orden mixta conserva la atencion y la recarga correctas para cada equipo', function () {
    $recarga = Service::factory()->create(['nombre' => 'Recarga CO2 5 lb']);
    $mantenimiento = Service::factory()->create(['nombre' => 'Mantenimiento PQS 6 kg']);
    $orden = ServiceOrder::factory()->create(['service_id' => $recarga->id]);
    $lineaRecarga = $orden->lines()->create(['service_id' => $recarga->id, 'cantidad' => 1]);
    $lineaMantenimiento = $orden->lines()->create(['service_id' => $mantenimiento->id, 'cantidad' => 1]);
    $equipoCo2 = Equipment::factory()->create(['client_id' => $orden->client_id, 'tipo_agente' => 'CO2']);
    $equipoPqs = Equipment::factory()->create(['client_id' => $orden->client_id, 'tipo_agente' => 'PQS ABC']);
    $orden->equipments()->attach($equipoCo2->id, ['service_order_line_id' => $lineaRecarga->id]);
    $orden->equipments()->attach($equipoPqs->id, ['service_order_line_id' => $lineaMantenimiento->id]);

    expect(TipoAtencionExtintor::deOrden($orden))->toBe('recarga / mantenimiento')
        ->and(TipoAtencionExtintor::deEquipoEnOrden($orden, $orden->equipments()->whereKey($equipoCo2->id)->firstOrFail()))->toBe('recarga')
        ->and(TipoAtencionExtintor::deEquipoEnOrden($orden, $orden->equipments()->whereKey($equipoPqs->id)->firstOrFail()))->toBe('mantenimiento');
});

test('corregir solo la atencion crea una revision y conserva numero vigencia y datos de ph', function () {
    $this->travelTo('2026-10-12');
    $tipo = CertificateType::factory()->create(['codigo' => 'prueba_hidrostatica', 'vigencia_meses' => 60]);
    $equipo = Equipment::factory()->create();
    $unidades = [[
        'equipment_id' => $equipo->id,
        'numero_serie_snapshot' => 'FAB-401',
        'fecha_ultima_ph' => '2025-07-15',
        'fecha_ultima_recarga' => '2026-09-12',
        'presion_ph' => '500 PSI',
        'tiempo_ph' => '30 s',
        'presion_trabajo' => '195 PSI',
    ]];
    $action = app(IssueCertificate::class);
    $original = $action->handle($tipo, $equipo->client, $unidades, extra: ['tipo_atencion' => 'venta']);
    $numero = $original->numero;
    $vigencia = $original->fecha_vigencia_hasta->toDateString();

    $corregido = $action->corregir($original, $unidades, ['tipo_atencion' => 'nuevo'], 'Aclarar atención realizada');

    $this->assertDatabaseHas('certificates', ['id' => $corregido->id, 'tipo_atencion' => 'nuevo', 'revision' => 1, 'numero' => $numero, 'fecha_vigencia_hasta' => $vigencia]);
    $revision = $corregido->revisions()->sole();
    expect($revision->antes['tipo_atencion'])->toBe('venta');
    expect($revision->despues['tipo_atencion'])->toBe('nuevo');
    $this->assertDatabaseHas('certificate_units', ['certificate_id' => $corregido->id, 'numero_serie_snapshot' => 'FAB-401', 'fecha_ultima_ph' => '2025-07-15', 'presion_ph' => '500 PSI', 'tiempo_ph' => '30 s', 'presion_trabajo' => '195 PSI']);

    $sinCambios = $action->corregir($corregido, $unidades, ['tipo_atencion' => 'nuevo'], 'Revisar sin cambios');

    expect($sinCambios->revision)->toBe(1);
    expect($sinCambios->revisions()->count())->toBe(1);
});

test('editar el servicio actualiza la atencion de su grupo sin regenerar la ph ni los datos tecnicos', function () {
    $this->travelTo('2026-10-12');
    $sale = Sale::factory()->create(['estado' => 'confirmada', 'comprobante_tipo' => 'nota_venta', 'numero_nota_venta' => 'NV-0099', 'medio_pago' => null]);
    [$corregido, $sinCambios] = Equipment::factory()->count(2)->create(['client_id' => $sale->client_id, 'capacidad' => '6 kg', 'marca' => 'ORIGINAL']);
    $recarga = Service::factory()->create(['nombre' => 'Recarga PQS 6 KG']);
    $mantenimiento = Service::factory()->create(['nombre' => 'Mantenimiento PQS 6 KG']);
    SaleItem::factory()->forService($recarga)->create(['sale_id' => $sale->id, 'equipment_id' => $corregido->id]);
    SaleItem::factory()->forService($recarga)->create(['sale_id' => $sale->id, 'equipment_id' => $sinCambios->id]);
    $operatividad = CertificateType::factory()->create(['codigo' => 'operatividad_garantia']);
    $tipoPh = CertificateType::factory()->create(['codigo' => 'prueba_hidrostatica', 'vigencia_meses' => 60]);
    $tipoCapacitacion = CertificateType::factory()->create(['codigo' => 'capacitacion', 'vigencia_meses' => 0]);
    $action = app(IssueCertificate::class);
    $unidades = [[
        'equipment_id' => $corregido->id,
        'numero_cliente' => '07',
        'numero_serie_snapshot' => 'FAB-ORIGINAL',
        'fecha_ultima_ph' => '2025-07-15',
        'fecha_ultima_recarga' => '2026-08-10',
        'presion_ph' => '500 PSI',
        'tiempo_ph' => '30 s',
        'presion_trabajo' => '195 PSI',
    ]];
    $grupoCorregido = $action->handle($operatividad, $sale->client, $unidades, saleId: $sale->id, extra: ['tipo_atencion' => 'recarga', 'referencia' => 'SEDE A', 'direccion' => 'DIRECCION A']);
    $grupoSinCambios = $action->handle($operatividad, $sale->client, [['equipment_id' => $sinCambios->id, 'numero_cliente' => '11']], saleId: $sale->id, extra: ['tipo_atencion' => 'recarga', 'referencia' => 'SEDE B']);
    $ph = $action->handle($tipoPh, $sale->client, $unidades, saleId: $sale->id, extra: ['tipo_atencion' => 'recarga']);
    $capacitacion = $action->handle($tipoCapacitacion, $sale->client, [], saleId: $sale->id, extra: ['tipo_atencion' => 'recarga', 'datos' => ['curso' => 'USO Y MANEJO DE EXTINTORES']]);
    $datosGrupoAntes = $grupoCorregido->only(['numero', 'fecha_emision', 'fecha_vigencia_hasta', 'referencia', 'direccion']);
    $unidadesAntes = $grupoCorregido->certificateUnits->toArray();
    $phAntes = $ph->fresh('certificateUnits')->toArray();
    $capacitacionAntes = $capacitacion->fresh('certificateUnits')->toArray();
    $corregido->update(['marca' => 'MARCA ACTUAL', 'serie_fabricante' => 'FAB-ACTUAL']);

    app(EditarVentaEmitida::class)->handle($sale, [
        'client_id' => $sale->client_id,
        'sede_id' => $sale->sede_id,
        'destino' => $sale->destino,
        'referencia' => $sale->referencia,
        'condicion_pago' => 'contado',
        'medio_pago' => null,
        'comprobante_tipo' => 'nota_venta',
    ], [
        ['tipo_linea' => 'recarga_servicio', 'numero_serie' => $corregido->numero_serie, 'service_id' => $mantenimiento->id, 'cantidad' => 1, 'precio_unitario' => 100],
        ['tipo_linea' => 'recarga_servicio', 'numero_serie' => $sinCambios->numero_serie, 'service_id' => $recarga->id, 'cantidad' => 1, 'precio_unitario' => 100],
    ], $sale->vendedor_id);

    $actualizado = $grupoCorregido->fresh('certificateUnits');
    $this->assertDatabaseHas('certificates', ['id' => $actualizado->id, 'tipo_atencion' => 'mantenimiento', 'revision' => 1, 'estado' => 'vigente']);
    expect($actualizado->only(['numero', 'fecha_emision', 'fecha_vigencia_hasta', 'referencia', 'direccion']))->toEqual($datosGrupoAntes);
    expect($actualizado->certificateUnits->toArray())->toBe($unidadesAntes);
    expect($actualizado->revisions()->sole()->user_id)->toBe($sale->vendedor_id);
    $this->assertDatabaseHas('certificates', ['id' => $grupoSinCambios->id, 'tipo_atencion' => 'recarga', 'revision' => 0, 'referencia' => 'SEDE B']);
    expect($ph->fresh('certificateUnits')->toArray())->toBe($phAntes);
    expect($capacitacion->fresh('certificateUnits')->toArray())->toBe($capacitacionAntes);
});

test('editar el cliente y retirar los equipos de la venta conserva la ph original reutilizada', function () {
    $this->travelTo('2026-10-12');
    $sale = Sale::factory()->create(['estado' => 'confirmada', 'comprobante_tipo' => 'nota_venta', 'numero_nota_venta' => 'NV-0099', 'medio_pago' => null]);
    $equipo = Equipment::factory()->create(['client_id' => $sale->client_id]);
    $servicio = Service::factory()->create(['nombre' => 'Recarga PQS']);
    SaleItem::factory()->forService($servicio)->create(['sale_id' => $sale->id, 'equipment_id' => $equipo->id]);
    $tipoPh = CertificateType::factory()->create(['codigo' => 'prueba_hidrostatica', 'vigencia_meses' => 60]);
    $ph = app(IssueCertificate::class)->handle($tipoPh, $sale->client, [[
        'equipment_id' => $equipo->id,
        'numero_serie_snapshot' => 'PH-ORIGINAL',
        'fecha_ultima_ph' => '2025-07-15',
        'presion_ph' => '500 PSI',
        'tiempo_ph' => '30 s',
    ]], saleId: $sale->id);
    $otraVenta = Sale::factory()->create(['client_id' => $sale->client_id, 'estado' => 'confirmada']);
    $ph->ventasReutilizadas()->attach($otraVenta->id);
    $original = $ph->fresh('certificateUnits')->toArray();
    $nuevoCliente = Client::factory()->create();

    app(EditarVentaEmitida::class)->handle($sale, [
        'client_id' => $nuevoCliente->id,
        'sede_id' => $sale->sede_id,
        'destino' => $sale->destino,
        'condicion_pago' => 'contado',
        'medio_pago' => null,
        'comprobante_tipo' => 'nota_venta',
    ], [
        ['tipo_linea' => 'servicio', 'service_id' => $servicio->id, 'cantidad' => 1, 'precio_unitario' => 100],
    ], $sale->vendedor_id);

    expect($ph->fresh('certificateUnits')->toArray())->toBe($original);
    expect($ph->ventasReutilizadas()->whereKey($otraVenta->id)->exists())->toBeTrue();
    $this->assertDatabaseHas('sales', ['id' => $sale->id, 'client_id' => $nuevoCliente->id]);
});
