<?php

use App\Actions\Almacen\TransferInventory;
use App\Actions\Billing\CreateDispatchGuide;
use App\Contracts\GreClientInterface;
use App\Enums\TeamRole;
use App\Models\Client;
use App\Models\CompanySetting;
use App\Models\DispatchGuide;
use App\Models\Driver;
use App\Models\InventoryMovement;
use App\Models\InventoryTransfer;
use App\Models\InventoryUnit;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Sede;
use App\Models\Team;
use App\Models\TransportVehicle;
use App\Models\User;
use App\Services\Billing\GreApiClient;
use App\Services\Billing\GuiaRemisionService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Fase H. SUNAT se simula siempre: nunca se llama a la red real.
 */
beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Storage::fake('local');
    config([
        'billing.sunat.cert_path' => base_path('tests/Fixtures/certificates/test-certificate.pem'),
        'billing.sunat.ruc' => '20600000001',
    ]);
    CompanySetting::factory()->create();

    $this->sede = Sede::factory()->create(['direccion' => 'Av. España 100', 'cod_establecimiento_anexo' => '0000']);
    $this->user = User::factory()->create(['sede_id' => $this->sede->id]);
    $this->user->assignRole('Vendedor');

    $this->sunat = new class implements GreClientInterface
    {
        /** @var array{en_proceso:bool,aceptada:bool,cdr_zip:string|null,codigo:string,mensaje:string} */
        public array $respuesta = ['en_proceso' => false, 'aceptada' => true, 'cdr_zip' => 'cdr-simulado', 'codigo' => '0', 'mensaje' => 'ok'];

        public int $enviados = 0;

        public function enviar(string $nombreArchivo, string $zip): string
        {
            $this->enviados++;
            expect($nombreArchivo)->toStartWith('20600000001-09-T001-')
                ->and($zip)->toStartWith('PK');

            return 'ticket-simulado';
        }

        public function consultar(string $ticket): array
        {
            expect($ticket)->toBe('ticket-simulado');

            return $this->respuesta;
        }
    };
    $this->app->instance(GreClientInterface::class, $this->sunat);
});

function datosGuia(array $extra = []): array
{
    return [
        'sede_id' => test()->sede->id,
        'fecha_traslado' => now()->toDateString(),
        'motivo' => '01',
        'modalidad' => '02',
        'destinatario_tipo_doc' => '6',
        'destinatario_num_doc' => '20123456786',
        'destinatario_nombre' => 'CLIENTE SAC',
        'partida_ubigeo' => '130101',
        'partida_direccion' => 'Av. España 100',
        'partida_cod_establecimiento' => '0000',
        'llegada_ubigeo' => '130101',
        'llegada_direccion' => 'Jr. Pizarro 200',
        'peso_bruto' => 12.5,
        'transport_vehicle_id' => TransportVehicle::factory()->create()->id,
        'driver_id' => Driver::factory()->create()->id,
        'doc_relacionado_tipo' => '01',
        'doc_relacionado_numero' => 'F001-15',
        'items' => [['codigo' => 'EXT-6', 'descripcion' => 'Extintor PQS 6 kg', 'unidad' => 'NIU', 'cantidad' => 2, 'peso_kg' => 12.5]],
        ...$extra,
    ];
}

test('la guía nace con serie T001, tipo 09 y correlativo consecutivo', function () {
    $primera = app(CreateDispatchGuide::class)->handle(datosGuia(), $this->user);
    $segunda = app(CreateDispatchGuide::class)->handle(datosGuia(), $this->user);

    expect($primera->serie)->toBe('T001')
        ->and([$primera->correlativo, $segunda->correlativo])->toBe([1, 2])
        ->and($primera->estado_sunat)->toBe(DispatchGuide::BORRADOR)
        ->and($primera->estaListaParaTrasladar())->toBeFalse();
});

test('el XML firmado cumple lo básico de la GRE y solo el CDR aceptado la deja lista para trasladar', function () {
    $guia = app(CreateDispatchGuide::class)->handle(datosGuia(), $this->user);
    $servicio = app(GuiaRemisionService::class);

    $guia = $servicio->enviar($guia);

    expect($guia->estado_sunat)->toBe(DispatchGuide::ENVIADA)
        ->and($guia->sunat_ticket)->toBe('ticket-simulado')
        ->and($guia->hash_zip)->toHaveLength(64)
        ->and($guia->estaListaParaTrasladar())->toBeFalse();

    $xml = Storage::disk('local')->get($guia->xml_path);
    expect($xml)->toContain('DespatchAdvice')
        ->and($xml)->toContain('T001-1')
        ->and($xml)->toContain('130101');

    $this->sunat->respuesta = ['en_proceso' => true, 'aceptada' => false, 'cdr_zip' => null, 'codigo' => '98', 'mensaje' => 'proceso'];
    expect($servicio->consultar($guia)->estaListaParaTrasladar())->toBeFalse();

    $this->sunat->respuesta = ['en_proceso' => false, 'aceptada' => true, 'cdr_zip' => 'cdr-simulado', 'codigo' => '0', 'mensaje' => 'ok'];
    $guia = $servicio->consultar($guia);

    expect($guia->estaListaParaTrasladar())->toBeTrue()
        ->and(Storage::disk('local')->exists($guia->cdr_path))->toBeTrue();
});

test('una guía rechazada por SUNAT no queda lista para trasladar', function () {
    $servicio = app(GuiaRemisionService::class);
    $guia = $servicio->enviar(app(CreateDispatchGuide::class)->handle(datosGuia(), $this->user));

    $this->sunat->respuesta = ['en_proceso' => false, 'aceptada' => false, 'cdr_zip' => null, 'codigo' => '99', 'mensaje' => 'Dato inválido'];
    $guia = $servicio->consultar($guia);

    expect($guia->estado_sunat)->toBe(DispatchGuide::RECHAZADA)
        ->and($guia->estaListaParaTrasladar())->toBeFalse()
        ->and($guia->sunat_mensaje)->toBe('Dato inválido');
});

test('un error de comunicación al consultar no rechaza la guía: sigue enviada y se reintenta', function () {
    $servicio = app(GuiaRemisionService::class);
    $guia = $servicio->enviar(app(CreateDispatchGuide::class)->handle(datosGuia(), $this->user));

    $this->sunat->respuesta = ['en_proceso' => false, 'aceptada' => false, 'cdr_zip' => null, 'codigo' => GreApiClient::SIN_RESPUESTA, 'mensaje' => 'SUNAT no respondió al consultar el ticket.'];
    $guia = $servicio->consultar($guia);

    expect($guia->estado_sunat)->toBe(DispatchGuide::ENVIADA)
        ->and($guia->estaListaParaTrasladar())->toBeFalse();

    // Cuando SUNAT por fin responde, se acepta sin haberla vuelto a enviar.
    $this->sunat->respuesta = ['en_proceso' => false, 'aceptada' => true, 'cdr_zip' => 'cdr-simulado', 'codigo' => '0', 'mensaje' => 'ok'];
    $guia = $servicio->consultar($guia);

    expect($guia->estado_sunat)->toBe(DispatchGuide::ACEPTADA)
        ->and($this->sunat->enviados)->toBe(1);
});

test('el transporte privado exige vehículo y conductor y el peso debe ser mayor que cero', function () {
    $team = Team::factory()->create();
    $this->user->update(['current_team_id' => $team->id]);
    $team->members()->attach($this->user, ['role' => TeamRole::Admin->value]);

    $this->actingAs($this->user)->post(route('guias.store', $team), datosGuia([
        'transport_vehicle_id' => null,
        'driver_id' => null,
        'peso_bruto' => 0,
    ]))->assertSessionHasErrors(['transport_vehicle_id', 'driver_id', 'peso_bruto']);

    expect(DispatchGuide::count())->toBe(0);
});

test('un vendedor no ve ni envía guías de otra sede', function () {
    $team = Team::factory()->create();
    $this->user->update(['current_team_id' => $team->id]);
    $team->members()->attach($this->user, ['role' => TeamRole::Admin->value]);
    $otraSede = Sede::factory()->create();
    $ajena = app(CreateDispatchGuide::class)->handle(datosGuia(['sede_id' => $otraSede->id]), $this->user);

    $this->actingAs($this->user)->post(route('guias.enviar', [$team, $ajena]))->assertNotFound();
    expect($this->sunat->enviados)->toBe(0);
});

test('el traslado entre sedes queda en tránsito hasta que el destino confirma', function () {
    $origen = Sede::factory()->create(['tipo' => 'almacen']);
    $destino = Sede::factory()->create(['tipo' => 'almacen']);
    $almacenero = User::factory()->create(['sede_id' => $origen->id]);
    $unidad = InventoryUnit::factory()->create(['sede_almacen_id' => $origen->id, 'estado' => 'disponible']);
    $producto = Product::factory()->create(['serializado' => false]);
    InventoryMovement::create(['product_id' => $producto->id, 'sede_id' => $origen->id, 'tipo' => 'ingreso', 'cantidad' => 10, 'user_id' => $almacenero->id]);

    $traslado = app(TransferInventory::class)->handle($origen->id, ['destination_sede_id' => $destino->id, 'serials' => [$unidad->numero_serie]], $almacenero);
    app(TransferInventory::class)->handle($origen->id, ['destination_sede_id' => $destino->id, 'product_id' => $producto->id, 'quantity' => 4], $almacenero);

    expect($traslado->estado)->toBe(InventoryTransfer::EN_TRANSITO)
        ->and($unidad->refresh()->estado)->toBe('en_transito')
        ->and((int) InventoryMovement::where('product_id', $producto->id)->where('sede_id', $destino->id)->sum('cantidad'))->toBe(0);

    $destinatario = User::factory()->create(['sede_id' => $destino->id]);
    $transferencias = InventoryTransfer::query()->get();
    foreach ($transferencias as $pendiente) {
        app(TransferInventory::class)->confirmar($pendiente, $destinatario);
    }

    expect($unidad->refresh()->estado)->toBe('disponible')
        ->and($unidad->sede_almacen_id)->toBe($destino->id)
        ->and((int) InventoryMovement::where('product_id', $producto->id)->where('sede_id', $destino->id)->sum('cantidad'))->toBe(4)
        ->and((int) InventoryMovement::where('product_id', $producto->id)->sum('cantidad'))->toBe(10);

    expect(fn () => app(TransferInventory::class)->confirmar($transferencias->first(), $destinatario))
        ->toThrow(ValidationException::class);
});

test('la guía guarda el cliente de la venta u orden que la sustenta', function () {
    // Un traslado entre sedes no tiene cliente: es interno.
    $interna = app(CreateDispatchGuide::class)->handle(datosGuia(), $this->user);
    expect($interna->client_id)->toBeNull();

    // La guía de una venta nace con el cliente de esa venta, aunque el
    // snapshot del destinatario siga siendo lo que va a SUNAT.
    $cliente = Client::factory()->create();
    $venta = Sale::factory()->create(['client_id' => $cliente->id]);
    $guia = app(CreateDispatchGuide::class)->handle(datosGuia(['sale_id' => $venta->id]), $this->user);

    expect($guia->client_id)->toBe($cliente->id)
        ->and($guia->client->id)->toBe($cliente->id)
        ->and($guia->destinatario_nombre)->toBe('CLIENTE SAC');

    // La clave foránea protege al cliente de una guía (aunque la guía no
    // tenga venta detrás).
    $soloGuia = Client::factory()->create();
    app(CreateDispatchGuide::class)->handle(datosGuia(['client_id' => $soloGuia->id]), $this->user);

    expect(fn () => $soloGuia->delete())->toThrow(QueryException::class);
    $this->assertDatabaseHas('clients', ['id' => $soloGuia->id]);
});
