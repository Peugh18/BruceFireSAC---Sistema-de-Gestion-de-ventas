<?php

use App\Actions\Almacen\CreateReception;
use App\Actions\Sales\CreateSale;
use App\Enums\EquipmentType;
use App\Models\Agente;
use App\Models\CertificateType;
use App\Models\Client;
use App\Models\Equipment;
use App\Models\InventoryUnit;
use App\Models\Product;
use App\Models\Sede;
use App\Models\Service;
use App\Models\ServiceOrder;
use App\Models\TipoDeServicio;
use App\Models\User;
use Database\Seeders\CertificateTypeSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->sede = Sede::factory()->mixta()->create();
    $this->gerente = User::factory()->create();
    $this->gerente->assignRole('Gerente');
});

test('el agente y la capacidad del producto pasan a la unidad al recibir y al equipo al vender', function () {
    $product = Product::factory()->create(['agente' => EquipmentType::Co2->value, 'capacidad' => '5 kg', 'controla_lote' => false]);

    $reception = app(CreateReception::class)->handle(
        ['proveedor' => 'Proveedor SAC', 'fecha' => today()->toDateString(), 'sede_almacen_id' => $this->sede->id],
        [['product_id' => $product->id, 'cantidad' => 1, 'cantidad_conforme' => 1, 'unidades' => [[]]]],
        $this->gerente,
    );
    $unit = InventoryUnit::where('product_id', $product->id)->firstOrFail();

    expect($reception->exists)->toBeTrue()
        ->and($unit->agente)->toBe('co2')
        ->and($unit->capacidad)->toBe('5 kg');

    $vendedor = User::factory()->create(['sede_id' => $this->sede->id]);
    $vendedor->assignRole('Vendedor');

    app(CreateSale::class)->handle([
        'client_id' => Client::factory()->create()->id,
        'sede_id' => $this->sede->id,
        'fecha' => today()->toDateString(),
        'destino' => 'local_cliente',
        'condicion_pago' => 'contado',
        'comprobante_tipo' => 'nota_venta',
    ], [[
        'tipo_linea' => 'unidad_nueva',
        'numero_serie' => $unit->numero_serie,
        'product_id' => $product->id,
        'cantidad' => 1,
        'precio_unitario' => 120,
    ]], $vendedor->id);

    $equipo = Equipment::where('numero_serie', $unit->numero_serie)->firstOrFail();

    expect($equipo->tipo_agente)->toBe('CO2')
        ->and($equipo->capacidad)->toBe('5 kg');
});

test('el gerente guarda el agente y la capacidad del producto; un agente fuera de la lista no pasa', function () {
    $datos = ['codigo' => 'EXT-CO2-5', 'nombre' => 'Extintor CO2 5 kg', 'categoria' => 'extintor', 'unidad_medida' => 'NIU', 'precio_venta' => 250, 'serializado' => true];

    $this->actingAs($this->gerente)
        ->post(route('gerente.productos.store', ['current_team' => $this->gerente->currentTeam]), [...$datos, 'agente' => 'CO2', 'capacidad' => '5 kg'])
        ->assertSessionHasNoErrors();

    expect(Product::query()->where('nombre', 'Extintor CO2 5 kg')->first()->only(['agente', 'capacidad']))->toBe(['agente' => 'CO2', 'capacidad' => '5 kg']);

    $this->actingAs($this->gerente)
        ->post(route('gerente.productos.store', ['current_team' => $this->gerente->currentTeam]), [...$datos, 'codigo' => 'EXT-X', 'agente' => 'polvo raro'])
        ->assertSessionHasErrors('agente');
});

test('en el alta de un extintor el agente se elige de la lista, no se escribe a mano', function () {
    $vendedor = User::factory()->create(['sede_id' => $this->sede->id]);
    $vendedor->assignRole('Vendedor');
    $order = ServiceOrder::factory()->create(['sede_id' => $this->sede->id, 'service_id' => Service::factory()->create(['nombre' => 'Mantenimiento general', 'agente' => null, 'capacidad' => null])->id]);
    $ruta = route('vendedor.ordenes-servicio.equipos.store', ['current_team' => $vendedor->currentTeam, 'service_order' => $order]);

    $this->actingAs($vendedor)->post($ruta, ['tipo_agente' => 'Polvo químico', 'capacidad' => '6 kg'])->assertSessionHasErrors('tipo_agente');
    $this->actingAs($vendedor)->post($ruta, ['tipo_agente' => 'PQS BC', 'capacidad' => '6 kg'])->assertSessionHasNoErrors();

    expect($order->equipments()->first()->tipo_agente)->toBe('PQS BC');
});

test('la lista de agentes distingue PQS ABC de PQS BC', function () {
    expect(EquipmentType::fromDescription('Extintor PQS BC 6 kg'))->toBe(EquipmentType::PqsBc)
        ->and(EquipmentType::fromDescription('Extintor PQS ABC 6 kg'))->toBe(EquipmentType::Pqs)
        ->and(EquipmentType::esConocido('No legible / Pendiente de verificar'))->toBeFalse()
        ->and(EquipmentType::esConocido('PQS ABC'))->toBeTrue()
        ->and(EquipmentType::Co2->presionPruebaHidrostatica())->toBe('3000 PSI');
});

test('el gerente elige el certificado que emite un servicio (A4)', function () {
    $this->seed(CertificateTypeSeeder::class);
    $tipo = CertificateType::where('codigo', 'luces_emergencia')->firstOrFail();
    // El certificado viene del tipo de servicio.
    $tipoDeServicio = TipoDeServicio::query()->create(['nombre' => 'Luces de emergencia', 'certificate_type_id' => $tipo->id]);

    $this->actingAs($this->gerente)
        ->post(route('gerente.servicios.store', ['current_team' => $this->gerente->currentTeam]), [
            'tipo_de_servicio_id' => $tipoDeServicio->id,
            'codigo' => 'SRV-LUCES',
            'nombre' => 'Mantenimiento de luces de emergencia',
            'unidad_medida' => 'ZZ',
            'precio_venta' => 80,
        ])
        ->assertSessionHasNoErrors();

    $servicio = Service::query()->latest('id')->firstOrFail();
    expect($servicio->certificate_type_id)->toBe($tipo->id);

    $this->actingAs($this->gerente)
        ->put(route('gerente.servicios.update', ['current_team' => $this->gerente->currentTeam, 'servicio' => $servicio]), [
            'codigo' => 'SRV-LUCES',
            'nombre' => 'Mantenimiento de luces de emergencia',
            'unidad_medida' => 'ZZ',
            'precio_venta' => 80,
            'certificate_type_id' => null,
        ])
        ->assertSessionHasNoErrors();

    expect($servicio->fresh()->certificate_type_id)->toBeNull();
});

test('un agente agregado a la lista se usa en el producto y el servicio lo reconoce por su tipo', function () {
    Agente::query()->create(['nombre' => 'Halotrón', 'tipo_base' => 'agente_limpio', 'orden' => 99]);
    $datos = ['nombre' => 'Extintor Halotrón 2 kg', 'categoria' => 'extintor', 'unidad_medida' => 'NIU', 'precio_venta' => 300, 'serializado' => true];

    $this->actingAs($this->gerente)
        ->post(route('gerente.productos.store', ['current_team' => $this->gerente->currentTeam]), [...$datos, 'agente' => 'Halotrón', 'capacidad' => '2 kg'])
        ->assertSessionHasNoErrors();

    $servicio = Service::factory()->create(['nombre' => 'Recarga 2 kg', 'agente' => 'Halotrón', 'capacidad' => '2 kg']);

    expect(Product::query()->where('nombre', 'Extintor Halotrón 2 kg')->value('agente'))->toBe('Halotrón')
        ->and($servicio->incompatibilidadConEquipo(Equipment::factory()->make(['tipo_agente' => 'Halotrón', 'capacidad' => '2 kg'])))->toBeNull()
        ->and($servicio->incompatibilidadConEquipo(Equipment::factory()->make(['tipo_agente' => 'CO2', 'capacidad' => '2 kg'])))->not->toBeNull();
});
