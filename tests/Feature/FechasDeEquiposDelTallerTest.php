<?php

use App\Actions\Certificates\IssueCertificate;
use App\Actions\Equipment\QuickRegisterEquipment;
use App\Actions\Sales\ProcessSaleItem;
use App\Actions\TecnicoCampo\EquipoDeLaOrden;
use App\Models\CertificateType;
use App\Models\CompanySetting;
use App\Models\Equipment;
use App\Models\InventoryUnit;
use App\Models\Sale;
use App\Models\ServiceOrder;
use App\Services\Certificates\CertificateDocumentData;
use Database\Seeders\CertificateTypeSeeder;
use Illuminate\Validation\ValidationException;

beforeEach(fn () => $this->travelTo('2026-10-23 12:00:00'));

test('el intervalo de empresa tambien se aplica a venta nueva e instalacion', function () {
    CompanySetting::current()->update(['intervalo_ph_anios' => 4]);
    $unidad = InventoryUnit::factory()->create();
    $venta = Sale::factory()->create(['sede_id' => $unidad->sede_almacen_id, 'fecha' => '2026-10-23']);
    $linea = app(ProcessSaleItem::class)->handle($venta, [
        'tipo_linea' => 'unidad_nueva', 'numero_serie' => $unidad->numero_serie,
        'product_id' => $unidad->product_id, 'cantidad' => 1, 'precio_unitario' => 100, 'subtotal' => 100,
    ]);
    $orden = ServiceOrder::factory()->create();
    $instalado = app(EquipoDeLaOrden::class)->resolver($orden, ['tipo_agente' => 'PQS'], true);
    expect($linea->equipment->proxima_prueba_hidrostatica->toDateString())->toBe('2030-10-23')
        ->and($instalado->proxima_prueba_hidrostatica->toDateString())->toBe('2030-10-23')
        ->and($instalado->fecha_ultima_ph)->toBeNull();
});

test('registrar un extintor del cliente conserva la fecha real de ph y no inventa venta ni recarga', function () {
    $order = ServiceOrder::factory()->create(['service_id' => null]);
    $equipo = app(QuickRegisterEquipment::class)->execute($order, [
        'serie_fabricante' => 'CIL-2019', 'anio_fabricacion' => '2019', 'fecha_ultima_ph' => '2023-06-10',
    ]);
    expect($equipo->fecha_venta)->toBeNull()
        ->and($equipo->proxima_fecha_atencion)->toBeNull()
        ->and($equipo->fecha_ultima_ph?->toDateString())->toBe('2023-06-10')
        ->and($equipo->proxima_prueba_hidrostatica?->toDateString())->toBe('2028-06-10');
});

test('sin fecha legible del cilindro la proxima ph queda desconocida', function () {
    $order = ServiceOrder::factory()->create(['service_id' => null]);
    $equipo = app(QuickRegisterEquipment::class)->execute($order, ['serie_fabricante' => 'SIN-FECHA']);
    expect($equipo->fecha_venta)->toBeNull()->and($equipo->proxima_prueba_hidrostatica)->toBeNull();
});

test('las proximas fechas admiten un ajuste explicito sin cambiar la ph real', function () {
    $order = ServiceOrder::factory()->create(['service_id' => null]);
    $equipo = app(QuickRegisterEquipment::class)->execute($order, ['fecha_ultima_ph' => '2023-06-10', 'proxima_prueba_hidrostatica' => '2027-06-10', 'proxima_fecha_atencion' => '2027-02-01']);
    expect($equipo->fecha_ultima_ph->toDateString())->toBe('2023-06-10')
        ->and($equipo->proxima_prueba_hidrostatica->toDateString())->toBe('2027-06-10')
        ->and($equipo->proxima_fecha_atencion->toDateString())->toBe('2027-02-01');
});

test('el intervalo configurable se calcula desde la ph real y los documentos conservan su fecha emitida', function () {
    $this->seed(CertificateTypeSeeder::class);
    CompanySetting::current()->update(['intervalo_ph_anios' => 4]);
    $order = ServiceOrder::factory()->create(['service_id' => null]);
    $equipo = app(QuickRegisterEquipment::class)->execute($order, ['tipo_agente' => 'PQS', 'fecha_ultima_ph' => '2023-06-10']);
    expect($equipo->proxima_prueba_hidrostatica->toDateString())->toBe('2027-06-10');
    $certificado = app(IssueCertificate::class)->handle(CertificateType::where('codigo', 'operatividad_garantia')->sole(), $order->client, [['equipment_id' => $equipo->id]]);
    $antes = app(CertificateDocumentData::class)->desde($certificado)['filas'][0]['proxima_ph'];
    CompanySetting::current()->update(['intervalo_ph_anios' => 8]);
    $equipo->update(['proxima_prueba_hidrostatica' => '2035-01-01']);
    expect(app(CertificateDocumentData::class)->desde($certificado->fresh())['filas'][0]['proxima_ph'])->toBe($antes);

    app(IssueCertificate::class)->corregir($certificado->fresh(), [['equipment_id' => $equipo->id, 'numero_cliente' => '03']], [], 'Numeración');
    expect(app(CertificateDocumentData::class)->desde($certificado->fresh())['filas'][0]['proxima_ph'])->toBe($antes);
});

test('un certificado sin fecha conocida de ph no recibe una fecha futura al actualizar el equipo', function () {
    $this->seed(CertificateTypeSeeder::class);
    $equipo = Equipment::factory()->create(['tipo_agente' => 'PQS', 'proxima_prueba_hidrostatica' => null]);
    $certificado = app(IssueCertificate::class)->handle(CertificateType::where('codigo', 'operatividad_garantia')->sole(), $equipo->client, [['equipment_id' => $equipo->id]]);
    $equipo->update(['fecha_ultima_ph' => '2025-06-10', 'proxima_prueba_hidrostatica' => '2030-06-10']);
    expect(app(CertificateDocumentData::class)->desde($certificado->fresh())['filas'][0]['proxima_ph'])->toBe('—');
    app(IssueCertificate::class)->corregir($certificado->fresh(), [['equipment_id' => $equipo->id, 'numero_cliente' => '03']], [], 'Numeración');
    expect($certificado->fresh()->certificateUnits->sole()->fecha_ultima_ph)->toBeNull()
        ->and(app(CertificateDocumentData::class)->desde($certificado->fresh())['filas'][0]['proxima_ph'])->toBe('—');
});

test('el alta tecnica rechaza anos y fechas imposibles sin crear equipos', function (array $datos) {
    $order = ServiceOrder::factory()->create(['service_id' => null]);
    expect(fn () => app(QuickRegisterEquipment::class)->execute($order, $datos))->toThrow(ValidationException::class);
    expect($order->equipments()->count())->toBe(0);
})->with([
    'texto' => [['anio_fabricacion' => 'abc']],
    'futuro' => [['anio_fabricacion' => '2027']],
    'antiguo' => [['anio_fabricacion' => '1979']],
    'ph futura' => [['fecha_ultima_ph' => '2026-10-24']],
    'ph anterior al cilindro' => [['anio_fabricacion' => '2020', 'fecha_ultima_ph' => '2019-12-31']],
]);
