<?php

use App\Enums\TeamRole;
use App\Models\Certificate;
use App\Models\CertificateType;
use App\Models\Client;
use App\Models\Deficiency;
use App\Models\Equipment;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\Sede;
use App\Models\ServiceOrder;
use App\Models\ServiceOrderEvent;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Deja stock del repuesto en el almacén que abastece a la sede.
 */
function repuestoEnStock(Product $product, Sede $sede, int $cantidad = 10): void
{
    (new InventoryMovement)->forceFill([
        'product_id' => $product->id,
        'sede_id' => $sede->almacenEfectivoId(),
        'tipo' => 'ingreso',
        'cantidad' => $cantidad,
        'observacion' => 'Stock inicial de prueba',
    ])->save();
}

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->team = Team::factory()->create();
    $this->tecnicoPlanta = User::factory()->create(['current_team_id' => $this->team->id]);
    $this->team->members()->attach($this->tecnicoPlanta, ['role' => TeamRole::Admin->value]);
    $this->tecnicoPlanta->assignRole('TecnicoPlanta');
});

test('tecnico planta can view workshop execution view', function () {
    $client = Client::factory()->create();
    $order = ServiceOrder::factory()->create([
        'client_id' => $client->id,
        'codigo' => 'OS-EJEC-0001',
        'estado' => 'en_proceso',
    ]);

    $this->actingAs($this->tecnicoPlanta)
        ->get(route('tecnico-planta.ejecucion.show', [
            'current_team' => $this->team,
            'service_order' => $order,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tecnico-planta/ejecucion/show')
            ->where('order.codigo', 'OS-EJEC-0001')
            ->where('order.estado', 'en_proceso')
        );
});

test('consuming spare part generates real InventoryMovement in Kardex and resolves deficiency', function () {
    $sede = Sede::factory()->create(['activo' => true]);
    $client = Client::factory()->create();
    $order = ServiceOrder::factory()->create([
        'client_id' => $client->id,
        'sede_id' => $sede->id,
        'codigo' => 'OS-EJEC-0002',
        'estado' => 'en_proceso',
    ]);

    $product = Product::factory()->create([
        'nombre' => 'Manómetro 1/8 195 PSI',
        'serializado' => false,
    ]);
    repuestoEnStock($product, $sede);

    $deficiency = Deficiency::create([
        'service_order_id' => $order->id,
        'componente' => 'Manómetro',
        'condicion' => 'Roto',
        'estado' => 'autorizada',
    ]);

    $this->actingAs($this->tecnicoPlanta)
        ->post(route('tecnico-planta.ejecucion.consume-spare', [
            'current_team' => $this->team,
            'service_order' => $order,
            'deficiency' => $deficiency,
        ]), [
            'product_id' => $product->id,
            'cantidad' => 2,
            'observacion' => 'Instalado con teflón alta presión',
        ])
        ->assertRedirect();

    // Verificación de movimiento real en Kardex (regla crítica: nunca descuento fuera del Kardex)
    $movement = InventoryMovement::where('product_id', $product->id)
        ->where('referencia_id', $deficiency->id)
        ->first();

    expect($movement)->not->toBeNull()
        ->and($movement->tipo)->toBe('salida_servicio')
        ->and($movement->cantidad)->toBe(-2)
        ->and($movement->observacion)->toContain('Consumo en taller')
        ->and($movement->user_id)->toBe($this->tecnicoPlanta->id);

    // Deficiencia resuelta
    $deficiency->refresh();
    expect($deficiency->estado)->toBe('resuelta')
        ->and($deficiency->resolucion)->toContain('Manómetro 1/8 195 PSI (x2)');

    // Bitácora registrada
    expect(ServiceOrderEvent::where('service_order_id', $order->id)->where('tipo', 'otro')->exists())->toBeTrue();
});

test('order is released from esperando_autorizacion once its last pending deficiency is resolved', function () {
    $sede = Sede::factory()->create(['activo' => true]);
    $client = Client::factory()->create();
    $order = ServiceOrder::factory()->create([
        'client_id' => $client->id,
        'sede_id' => $sede->id,
        'codigo' => 'OS-EJEC-0004',
        'estado' => 'esperando_autorizacion',
    ]);

    $product = Product::factory()->create(['nombre' => 'Manguera 1.5 pulg', 'serializado' => false]);
    repuestoEnStock($product, $sede);

    // Una deficiencia ya autorizada por Vendedor, lista para que Planta la resuelva
    $deficiency = Deficiency::create([
        'service_order_id' => $order->id,
        'componente' => 'Manguera',
        'condicion' => 'Rota',
        'requiere_autorizacion' => true,
        'estado' => 'autorizada',
    ]);

    // Otra deficiencia del mismo equipo que NO requiere autorizacion no debe bloquear el release
    Deficiency::create([
        'service_order_id' => $order->id,
        'componente' => 'Rotulado',
        'condicion' => 'Desgastado',
        'requiere_autorizacion' => false,
        'estado' => 'detectada',
    ]);

    $this->actingAs($this->tecnicoPlanta)
        ->post(route('tecnico-planta.ejecucion.consume-spare', [
            'current_team' => $this->team,
            'service_order' => $order,
            'deficiency' => $deficiency,
        ]), [
            'product_id' => $product->id,
            'cantidad' => 1,
        ])
        ->assertRedirect();

    // La orden ya no debe quedar bloqueada en esperando_autorizacion (bug real
    // encontrado en auditoria E2E: sin esto la orden nunca podia cerrarse).
    $order->refresh();
    expect($order->estado)->toBe('autorizado');
});

test('order stays in esperando_autorizacion while another deficiency is still pending authorization', function () {
    $sede = Sede::factory()->create(['activo' => true]);
    $client = Client::factory()->create();
    $order = ServiceOrder::factory()->create([
        'client_id' => $client->id,
        'sede_id' => $sede->id,
        'codigo' => 'OS-EJEC-0005',
        'estado' => 'esperando_autorizacion',
    ]);

    $product = Product::factory()->create(['nombre' => 'Manguera 1.5 pulg', 'serializado' => false]);
    repuestoEnStock($product, $sede);

    $deficiencyResolved = Deficiency::create([
        'service_order_id' => $order->id,
        'componente' => 'Manguera',
        'condicion' => 'Rota',
        'requiere_autorizacion' => true,
        'estado' => 'autorizada',
    ]);

    // Una segunda deficiencia del mismo equipo TODAVIA esperando autorizacion de Vendedor
    Deficiency::create([
        'service_order_id' => $order->id,
        'componente' => 'Manómetro',
        'condicion' => 'Roto',
        'requiere_autorizacion' => true,
        'estado' => 'esperando_autorizacion',
    ]);

    $this->actingAs($this->tecnicoPlanta)
        ->post(route('tecnico-planta.ejecucion.consume-spare', [
            'current_team' => $this->team,
            'service_order' => $order,
            'deficiency' => $deficiencyResolved,
        ]), [
            'product_id' => $product->id,
            'cantidad' => 1,
        ])
        ->assertRedirect();

    $order->refresh();
    expect($order->estado)->toBe('esperando_autorizacion');
});

test('advancing order to listo_certificado automatically triggers certificates generation', function () {
    $client = Client::factory()->create();
    $order = ServiceOrder::factory()->create([
        'client_id' => $client->id,
        'codigo' => 'OS-EJEC-0003',
        'estado' => 'trabajo_terminado',
    ]);

    $equipment = Equipment::factory()->create([
        'client_id' => $client->id,
        'numero_serie' => 'BF-EQ-000333',
        'tipo_agente' => 'PQS ABC',
    ]);
    $order->equipments()->attach($equipment->id);

    CertificateType::firstOrCreate(
        ['codigo' => 'operatividad_garantia'],
        ['nombre' => 'Certificado de Operatividad y Garantía', 'vigencia_meses' => 12, 'generado_por_rol' => 'tecnico_planta']
    );
    CertificateType::firstOrCreate(
        ['codigo' => 'prueba_hidrostatica'],
        ['nombre' => 'Certificado de Prueba Hidrostática', 'vigencia_meses' => 60, 'generado_por_rol' => 'tecnico_planta']
    );

    $this->actingAs($this->tecnicoPlanta)
        ->post(route('tecnico-planta.ejecucion.advance', [
            'current_team' => $this->team,
            'service_order' => $order,
        ]), [
            'target_state' => 'listo_certificado',
            'ph_realizada' => true,
        ])
        ->assertRedirect();

    $order->refresh();
    expect($order->estado)->toBe('listo_certificado');

    // Comprobar certificados generados automáticamente
    $certificates = Certificate::where('service_order_id', $order->id)->get();
    expect($certificates)->toHaveCount(2);

    $certOperatividad = $certificates->firstWhere('certificateType.codigo', 'operatividad_garantia');
    expect($certOperatividad)->not->toBeNull()
        ->and($certOperatividad->certificateUnits)->toHaveCount(1)
        ->and($certOperatividad->certificateUnits->first()->numero_serie_snapshot)->toBe('BF-EQ-000333');

    $certPH = $certificates->firstWhere('certificateType.codigo', 'prueba_hidrostatica');
    expect($certPH)->not->toBeNull();

    // Evento append-only registrado
    expect(ServiceOrderEvent::where('service_order_id', $order->id)->where('tipo', 'trabajo_completado')->exists())->toBeTrue();
});
