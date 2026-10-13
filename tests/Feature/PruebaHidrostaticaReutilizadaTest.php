<?php

use App\Actions\Certificates\EmitirCertificadosDeVenta;
use App\Actions\TecnicoPlanta\ExecuteAndCloseServiceOrder;
use App\Enums\TeamRole;
use App\Models\Certificate;
use App\Models\CertificateType;
use App\Models\Client;
use App\Models\Equipment;
use App\Models\Evidencia;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Sede;
use App\Models\Service;
use App\Models\ServiceOrder;
use App\Models\Team;
use App\Models\TechnicalChecklist;
use App\Models\User;
use App\Services\Certificates\CertificadosDeVenta;
use Database\Seeders\CertificateTypeSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->seed([CertificateTypeSeeder::class, RolesAndPermissionsSeeder::class]);
    $this->travelTo(now()->setDate(2026, 10, 12)->startOfDay());
});

/** @return array{0: Sale, 1: Equipment, 2: Certificate} */
function ventaConPhAnterior(): array
{
    $sale = Sale::factory()->create(['estado' => 'confirmada', 'destino' => 'vehiculo']);
    $equipment = Equipment::factory()->create(['client_id' => $sale->client_id, 'proxima_prueba_hidrostatica' => '2029-05-20']);
    SaleItem::factory()->forService()->create(['sale_id' => $sale->id, 'equipment_id' => $equipment->id]);
    $origin = Sale::factory()->create(['client_id' => $sale->client_id, 'estado' => 'confirmada']);
    $certificate = Certificate::factory()->create([
        'certificate_type_id' => CertificateType::where('codigo', 'prueba_hidrostatica')->sole()->id,
        'client_id' => $sale->client_id,
        'sale_id' => $origin->id,
        'numero' => 'PH-2024-0001',
        'fecha_emision' => '2024-05-21',
        'fecha_vigencia_hasta' => '2029-05-20',
        'revision' => 2,
    ]);
    $certificate->certificateUnits()->create([
        'equipment_id' => $equipment->id, 'numero_serie_snapshot' => $equipment->numero_serie,
        'orden' => 1, 'numero_cliente' => '01', 'fecha_ultima_ph' => '2024-05-20',
        'presion_ph' => '450 psi', 'tiempo_ph' => '30 segundos',
    ]);

    return [$sale, $equipment, $certificate];
}

/** @return list<array<string, mixed>> */
function grupoPh(Equipment $equipment, array $datos = []): array
{
    return [['referencia' => 'PLACA: NUEVA', 'tipos' => ['prueba_hidrostatica'], 'unidades' => [['equipment_id' => $equipment->id, 'numero_cliente' => '99', ...$datos]]]];
}

test('una recarga reutiliza la ph original sin modificar fechas unidades qr ni revision', function () {
    [$sale, $equipment, $certificate] = ventaConPhAnterior();
    $certificate = $certificate->fresh();
    $original = $certificate->getRawOriginal();
    $units = $certificate->certificateUnits()->get()->toArray();

    $result = app(EmitirCertificadosDeVenta::class)->handle($sale, grupoPh($equipment));

    expect($result->pluck('id')->all())->toBe([$certificate->id]);
    expect($certificate->fresh()->getRawOriginal())->toBe($original);
    expect($certificate->certificateUnits()->get()->toArray())->toBe($units);
    expect(array_column(CertificadosDeVenta::deLaVenta($sale), 'id'))->toBe([$certificate->id]);
    $this->assertDatabaseCount('certificates', 1);
});

test('no reutiliza ph vencida anulada de otro cliente o equipo dado de baja', function (string $case) {
    [$sale, $equipment, $certificate] = ventaConPhAnterior();
    match ($case) {
        'vencida' => $certificate->update(['estado' => 'vencido']),
        'anulada' => $certificate->update(['estado' => 'anulado']),
        'fecha' => $certificate->update(['fecha_vigencia_hasta' => '2026-10-11']),
        'cinco_anios' => $certificate->certificateUnits()->update(['fecha_ultima_ph' => '2021-10-12']),
        'otro_cliente' => $certificate->update(['client_id' => Client::factory()->create()->id]),
        'baja' => $equipment->update(['estado' => 'baja']),
    };

    expect(fn () => app(EmitirCertificadosDeVenta::class)->handle($sale, grupoPh($equipment)))->toThrow(ValidationException::class);
    $this->assertDatabaseCount('certificates', 1);
})->with(['vencida', 'anulada', 'fecha', 'cinco_anios', 'otro_cliente', 'baja']);

test('en una venta mixta solo emite ph para el extintor sin prueba vigente', function () {
    [$sale, $equipment, $certificate] = ventaConPhAnterior();
    $other = Equipment::factory()->create(['client_id' => $sale->client_id]);
    SaleItem::factory()->forService()->create(['sale_id' => $sale->id, 'equipment_id' => $other->id]);
    $groups = grupoPh($equipment);
    $groups[0]['unidades'][] = ['equipment_id' => $other->id, 'fecha_ultima_ph' => '2026-10-10', 'presion_ph' => '450 psi', 'tiempo_ph' => '30 segundos', 'resultado_ph' => 'aprobado'];

    $result = app(EmitirCertificadosDeVenta::class)->handle($sale, $groups);

    expect($result)->toHaveCount(2);
    $new = $result->firstWhere('id', '!=', $certificate->id);
    expect($new->certificateUnits()->pluck('equipment_id')->all())->toBe([$other->id]);
    expect($new->fecha_vigencia_hasta->toDateString())->toBe('2031-10-10');
    expect($certificate->fresh()->revision)->toBe(2);
});

test('reorganizar la recarga conserva la ph original compartida y su historial tras vencer', function () {
    [$sale, $equipment, $certificate] = ventaConPhAnterior();
    app(EmitirCertificadosDeVenta::class)->handle($sale, grupoPh($equipment));

    app(EmitirCertificadosDeVenta::class)->handle($sale, [['tipos' => ['operatividad_garantia'], 'unidades' => [['equipment_id' => $equipment->id]]]]);

    expect($certificate->fresh()->estado)->toBe('vigente');
    $certificate->update(['estado' => 'anulado']);
    expect(array_column(CertificadosDeVenta::deLaVenta($sale), 'id'))->toContain($certificate->id);
});

test('cerrar mantenimiento adjunta la ph vigente sin renovar su vencimiento', function () {
    [$sale, $equipment, $certificate] = ventaConPhAnterior();
    $order = ServiceOrder::factory()->create(['client_id' => $sale->client_id, 'sale_id' => $sale->id, 'sede_id' => $sale->sede_id, 'estado' => 'trabajo_terminado']);
    $order->equipments()->attach($equipment->id, ['recibido' => true]);
    $user = User::factory()->create();
    TechnicalChecklist::create(['service_order_id' => $order->id, 'equipment_id' => $equipment->id, 'user_id' => $user->id, 'origen' => 'planta', 'items' => [], 'resultado_general' => 'conforme']);

    app(ExecuteAndCloseServiceOrder::class)->advanceState($order, 'listo_certificado', $user);

    expect(array_column(CertificadosDeVenta::deLaVenta($sale, true), 'id'))->toContain($certificate->id);
    expect($equipment->fresh()->proxima_prueba_hidrostatica->toDateString())->toBe('2029-05-20');
    expect($certificate->fresh()->service_order_id)->toBeNull();
    $this->assertDatabaseCount('certificates', 2);
});

test('el vendedor autorizado ve la ph reutilizada sin acceder a la venta de origen', function () {
    [$sale, $equipment, $certificate] = ventaConPhAnterior();
    $user = User::factory()->create(['sede_id' => $sale->sede_id]);
    $user->assignRole('Vendedor');
    $sale->update(['vendedor_id' => $user->id]);
    app(EmitirCertificadosDeVenta::class)->handle($sale, grupoPh($equipment));

    expect(Certificate::visiblePara($user)->whereKey($certificate->id)->exists())->toBeTrue();
    $outsider = User::factory()->create(['sede_id' => Sede::factory()->create()->id]);
    $outsider->assignRole('Vendedor');
    expect(Certificate::visiblePara($outsider)->whereKey($certificate->id)->exists())->toBeFalse();
});

test('una venta vehicular de recarga adjunta automaticamente la ph sin emitir otra', function () {
    [$sale, $equipment, $certificate] = ventaConPhAnterior();

    $result = app(EmitirCertificadosDeVenta::class)->automaticos($sale);

    expect($result->pluck('id')->all())->toBe([$certificate->id]);
    expect(array_column(CertificadosDeVenta::deLaVenta($sale), 'id'))->toBe([$certificate->id]);
    $this->assertDatabaseCount('certificates', 1);
});

test('una ph vencida permite una nueva prueba aprobada sin alterar la anterior', function () {
    [$sale, $equipment, $certificate] = ventaConPhAnterior();
    $certificate->update(['estado' => 'vencido', 'fecha_vigencia_hasta' => '2026-10-01']);

    $result = app(EmitirCertificadosDeVenta::class)->handle($sale, grupoPh($equipment, [
        'fecha_ultima_ph' => '2026-10-10', 'presion_ph' => '450 psi', 'tiempo_ph' => '30 segundos', 'resultado_ph' => 'aprobado',
    ]));

    expect($result)->toHaveCount(1);
    expect($result->first()->id)->not->toBe($certificate->id);
    expect($result->first()->fecha_vigencia_hasta->toDateString())->toBe('2031-10-10');
    expect($certificate->fresh()->estado)->toBe('vencido');
});

test('cerrar mantenimiento en campo adjunta la ph original a la orden y su venta', function () {
    Storage::fake('local');
    [$sale, $equipment, $certificate] = ventaConPhAnterior();
    $team = Team::factory()->create();
    $user = User::factory()->create(['sede_id' => $sale->sede_id, 'current_team_id' => $team->id]);
    $team->members()->attach($user, ['role' => TeamRole::Admin->value]);
    $user->assignRole('TecnicoCampo');
    $order = ServiceOrder::factory()->create([
        'client_id' => $sale->client_id, 'sale_id' => $sale->id, 'sede_id' => $sale->sede_id,
        'estado' => 'recibido_planta', 'departamento_tecnico' => 'campo',
        'service_id' => Service::factory()->create(['nombre' => 'Mantenimiento en sitio'])->id,
    ]);
    $order->equipments()->attach($equipment->id, ['recibido' => true]);
    TechnicalChecklist::create(['service_order_id' => $order->id, 'equipment_id' => $equipment->id, 'user_id' => $user->id, 'origen' => 'campo', 'items' => [], 'resultado_general' => 'conforme']);
    Evidencia::factory()->create(['service_order_id' => $order->id, 'etapa' => 'antes']);
    Evidencia::factory()->create(['service_order_id' => $order->id, 'etapa' => 'despues']);

    $this->actingAs($user)->post(route('tecnico-campo.mantenimientos.complete', ['current_team' => $team, 'service_order' => $order]), [
        'conformidad_nombre' => 'Jefe del cliente', 'conformidad_aceptada' => true, 'firma' => FIRMA_PNG,
    ])->assertSessionHasNoErrors();

    expect(array_column(CertificadosDeVenta::deLaVenta($sale, true), 'id'))->toContain($certificate->id);
    expect($certificate->fresh()->revision)->toBe(2);
    expect($equipment->fresh()->proxima_prueba_hidrostatica->toDateString())->toBe('2029-05-20');
});

test('el formulario muestra la ph original y evita duplicar grupos al reorganizar operatividad', function () {
    [$sale, $equipment, $certificate] = ventaConPhAnterior();
    $certificate->update(['sale_id' => $sale->id, 'referencia' => 'PLACA ANTERIOR']);
    $user = User::factory()->create(['sede_id' => $sale->sede_id]);
    $user->assignRole('Vendedor');
    $sale->update(['vendedor_id' => $user->id]);
    app(EmitirCertificadosDeVenta::class)->handle($sale, [[
        'referencia' => 'PLACA NUEVA', 'tipos' => ['operatividad_garantia'], 'unidades' => [['equipment_id' => $equipment->id]],
    ]]);

    $this->actingAs($user)->get(route('vendedor.ventas.certificados.create', ['current_team' => $user->currentTeam, 'sale' => $sale]))
        ->assertInertia(fn ($page) => $page
            ->component('vendedor/ventas/certificados')
            ->has('gruposActuales', 1)
            ->where('unidades.0.ph_reutilizable.numero', 'PH-2024-0001')
            ->where('unidades.0.ph_reutilizable.fecha_vigencia_hasta', '2029-05-20'));
});

test('la ph reutilizada se abre sin exponer venta comprobante ni enlace de correccion de otra sede', function () {
    [$sale, $equipment, $certificate] = ventaConPhAnterior();
    $user = User::factory()->create(['sede_id' => $sale->sede_id]);
    $user->assignRole('Vendedor');
    $sale->update(['vendedor_id' => $user->id]);
    app(EmitirCertificadosDeVenta::class)->handle($sale, grupoPh($equipment));

    $this->actingAs($user)->get(route('vendedor.certificados.show', ['current_team' => $user->currentTeam, 'certificate' => $certificate]))
        ->assertInertia(fn ($page) => $page
            ->component('vendedor/certificados/show')
            ->where('certificate.numero', 'PH-2024-0001')
            ->where('certificate.venta', null)
            ->where('certificate.corregir_url', null));
});

test('la pantalla tecnica muestra la ph reutilizada con su numero y vencimiento originales', function (string $role, string $routeName, string $department, string $prop) {
    [$sale, $equipment, $certificate] = ventaConPhAnterior();
    $user = User::factory()->create(['sede_id' => $sale->sede_id]);
    $user->assignRole($role);
    $order = ServiceOrder::factory()->create(['client_id' => $sale->client_id, 'sede_id' => $sale->sede_id, 'estado' => 'listo_certificado', 'departamento_tecnico' => $department]);
    $order->equipments()->attach($equipment->id, ['recibido' => true]);
    $certificate->ordenesReutilizadas()->attach($order->id);

    $this->actingAs($user)->get(route($routeName, ['current_team' => $user->currentTeam, 'service_order' => $order]))
        ->assertInertia(fn ($page) => $page->has($prop, 1)->where("{$prop}.0.numero", 'PH-2024-0001'));
})->with([
    'planta' => ['TecnicoPlanta', 'tecnico-planta.ejecucion.show', 'planta', 'certificates'],
    'mantenimiento campo' => ['TecnicoCampo', 'tecnico-campo.mantenimientos.show', 'campo', 'order.certificates'],
    'inspeccion campo' => ['TecnicoCampo', 'tecnico-campo.inspecciones.show', 'campo', 'order.certificates'],
]);

test('una ph nueva agrupada vence a los cinco anios de la prueba real mas antigua', function () {
    [$sale, $equipment, $certificate] = ventaConPhAnterior();
    $certificate->update(['estado' => 'anulado']);
    $other = Equipment::factory()->create(['client_id' => $sale->client_id]);
    SaleItem::factory()->forService()->create(['sale_id' => $sale->id, 'equipment_id' => $other->id]);
    $groups = grupoPh($equipment, ['fecha_ultima_ph' => '2026-10-10', 'presion_ph' => '450 psi', 'tiempo_ph' => '30 segundos', 'resultado_ph' => 'aprobado']);
    $groups[0]['unidades'][] = ['equipment_id' => $other->id, 'fecha_ultima_ph' => '2025-09-20', 'presion_ph' => '450 psi', 'tiempo_ph' => '30 segundos', 'resultado_ph' => 'aprobado'];

    $result = app(EmitirCertificadosDeVenta::class)->handle($sale, $groups);

    expect($result->first()->fecha_vigencia_hasta->toDateString())->toBe('2030-09-20');
});

test('reabrir certificados conserva el destino de cada grupo aunque difiera de la venta', function (string $saleDestination, string $groupDestination, array $types) {
    [$sale, $equipment] = ventaConPhAnterior();
    $user = User::factory()->create(['sede_id' => $sale->sede_id]);
    $user->assignRole('Vendedor');
    $sale->update(['vendedor_id' => $user->id, 'destino' => $saleDestination]);
    $groups = grupoPh($equipment);
    $groups[0]['destino'] = $groupDestination;
    $groups[0]['tipos'] = $types;

    $this->actingAs($user)->post(route('vendedor.ventas.certificados.store', ['current_team' => $user->currentTeam, 'sale' => $sale]), ['grupos' => $groups])->assertSessionHasNoErrors();

    $this->get(route('vendedor.ventas.certificados.create', ['current_team' => $user->currentTeam, 'sale' => $sale]))
        ->assertInertia(fn ($page) => $page
            ->has('gruposActuales', 1)
            ->where('gruposActuales.0.destino', $groupDestination)
            ->where('gruposActuales.0.tipos', $types));
})->with([
    'grupo vehiculo en venta local' => ['local_cliente', 'vehiculo', ['operatividad_garantia', 'prueba_hidrostatica']],
    'grupo local en venta vehiculo' => ['vehiculo', 'local_cliente', ['operatividad_garantia', 'capacitacion']],
]);

test('reabrir un grupo manual conserva los tipos elegidos sin certificar una atencion no realizada', function (string $destination, string $unselectedType) {
    [$sale, $equipment] = ventaConPhAnterior();
    $user = User::factory()->create(['sede_id' => $sale->sede_id]);
    $user->assignRole('Vendedor');
    $sale->update(['vendedor_id' => $user->id, 'destino' => $destination]);
    $groups = [['destino' => $destination, 'tipos' => ['operatividad_garantia'], 'unidades' => [['equipment_id' => $equipment->id]]]];
    $store = route('vendedor.ventas.certificados.store', ['current_team' => $user->currentTeam, 'sale' => $sale]);
    $this->actingAs($user)->post($store, ['grupos' => $groups])->assertSessionHasNoErrors();

    $response = $this->get(route('vendedor.ventas.certificados.create', ['current_team' => $user->currentTeam, 'sale' => $sale]));
    $response->assertInertia(fn ($page) => $page
        ->has('gruposActuales', 1)
        ->where('gruposActuales.0.tipos', ['operatividad_garantia']));
    $reopened = $response->viewData('page')['props']['gruposActuales'];
    $this->post($store, ['grupos' => $reopened])->assertSessionHasNoErrors();

    expect(Certificate::query()->where('sale_id', $sale->id)->whereHas('certificateType', fn ($query) => $query->where('codigo', $unselectedType))->exists())->toBeFalse();
})->with([
    'local sin capacitacion' => ['local_cliente', 'capacitacion'],
    'vehiculo sin nueva PH' => ['vehiculo', 'prueba_hidrostatica'],
]);
