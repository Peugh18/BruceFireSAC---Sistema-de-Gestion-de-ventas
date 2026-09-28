<?php

use App\Actions\TecnicoPlanta\ExecuteAndCloseServiceOrder;
use App\Models\CertificateType;
use App\Models\Client;
use App\Models\Equipment;
use App\Models\Service;
use App\Models\ServiceOrder;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('orden guarda el servicio del catalogo como fuente unica', function () {
    $seller = vendedorUser();
    $client = Client::factory()->create();
    $service = Service::factory()->create(['nombre' => 'Inspección anual']);

    $this->actingAs($seller)->post(route('vendedor.ordenes-servicio.store', ['current_team' => $seller->currentTeam]), ['client_id' => $client->id, 'service_id' => $service->id, 'fecha' => today()->toDateString(), 'departamento_tecnico' => 'campo'])->assertRedirect();

    $order = ServiceOrder::latest('id')->first();

    expect($order->service_id)->toBe($service->id)
        ->and($order->service->nombre)->toBe('Inspección anual');
});

test('certificado configurado para un servicio se emite desde la orden', function () {
    $seller = vendedorUser();
    $type = CertificateType::factory()->create(['codigo' => 'luces_orden', 'columnas' => [['clave' => 'item', 'titulo' => 'Ítem']]]);
    $service = Service::factory()->create(['certificate_type_id' => $type->id]);
    $order = ServiceOrder::factory()->create(['service_id' => $service->id, 'estado' => 'datos_completos']);

    app(ExecuteAndCloseServiceOrder::class)->advanceState($order, 'listo_certificado', $seller, ['certificate_data' => ['filas' => [['item' => '01']]]]);

    expect($order->certificates()->where('certificate_type_id', $type->id)->exists())->toBeTrue();
});

test('por vencer crea una cotizacion de recarga con el extintor ligado', function () {
    $seller = vendedorUser();
    $client = Client::factory()->create();
    $equipment = Equipment::factory()->create(['client_id' => $client->id]);
    Service::factory()->create(['nombre' => 'Recarga de extintor', 'activo' => true, 'precio_venta' => 50]);

    $this->actingAs($seller)->post(route('vendedor.alertas.ofrecer-recarga', ['current_team' => $seller->currentTeam]), ['client_id' => $client->id, 'equipment_ids' => [$equipment->id]])->assertRedirect();

    expect($equipment->quotes()->count())->toBe(1);
});
