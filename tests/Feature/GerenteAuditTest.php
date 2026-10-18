<?php

use App\Actions\Sales\CreateSale;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\InventoryUnit;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Sede;
use App\Models\User;
use App\Services\AuditLogger;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function createGerenteUserForAuditTest(): User
{
    $user = User::factory()->create();
    $user->assignRole('Gerente');

    return $user;
}

test('gerente puede acceder al visor de auditoría', function () {
    $gerente = createGerenteUserForAuditTest();

    $this->actingAs($gerente)
        ->get(route('gerente.auditoria.index', ['current_team' => $gerente->currentTeam]))
        ->assertOk();
});

test('vendedor recibe 403 al intentar acceder a auditoría de gerente', function () {
    $user = User::factory()->create();
    $user->assignRole('Vendedor');

    $this->actingAs($user)
        ->get(route('gerente.auditoria.index', ['current_team' => $user->currentTeam]))
        ->assertForbidden();
});

test('el visor de auditoría lista los registros existentes más recientes primero', function () {
    $gerente = createGerenteUserForAuditTest();

    AuditLog::factory()->create(['action' => 'venta.creada', 'created_at' => now()->subDays(2)]);
    AuditLog::factory()->create(['action' => 'stock.ajuste', 'created_at' => now()->subDay()]);
    $ultimo = AuditLog::factory()->create(['action' => 'orden.cerrada', 'created_at' => now()]);

    $response = $this->actingAs($gerente)
        ->get(route('gerente.auditoria.index', ['current_team' => $gerente->currentTeam]));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('registros.data.0.id', $ultimo->id)
        ->where('kpis.totalRegistros', 3)
    );
});

test('el visor de auditoría filtra por tipo de acción', function () {
    $gerente = createGerenteUserForAuditTest();

    AuditLog::factory()->create(['action' => 'venta.creada']);
    AuditLog::factory()->create(['action' => 'stock.ajuste']);

    $response = $this->actingAs($gerente)
        ->get(route('gerente.auditoria.index', ['current_team' => $gerente->currentTeam, 'accion' => 'stock.ajuste']));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('registros.data', fn ($registros) => collect($registros)->every(fn ($r) => $r['accion'] === 'Ajuste de inventario registrado'))
    );
});

test('el visor muestra el rol registrado y filtra sin reinterpretar los eventos antiguos', function () {
    $gerente = createGerenteUserForAuditTest();

    AuditLog::factory()->create([
        'action' => 'orden.entregada',
        'actor_role' => 'Técnico de Campo',
    ]);
    AuditLog::factory()->create([
        'action' => 'venta.creada',
        'actor_role' => 'Vendedor',
    ]);
    $antiguo = AuditLog::factory()->create([
        'action' => 'cliente.actualizado',
        'actor_role' => null,
    ]);

    $response = $this->actingAs($gerente)
        ->get(route('gerente.auditoria.index', [
            'current_team' => $gerente->currentTeam,
            'rol' => 'Técnico de Campo',
        ]));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('registros.data.0.accion', 'Entrega confirmada')
        ->where('registros.data.0.rol', 'Técnico de Campo')
        ->where('filters.rol', 'Técnico de Campo')
        ->where('roles', ['Técnico de Campo', 'Vendedor'])
    );

    $sinRol = $this->actingAs($gerente)
        ->get(route('gerente.auditoria.index', [
            'current_team' => $gerente->currentTeam,
            'rol' => 'sin_rol_registrado',
        ]));

    $sinRol->assertInertia(fn ($page) => $page
        ->where('registros.data.0.id', $antiguo->id)
        ->where('registros.data.0.rol', 'Sin rol registrado')
    );
});

test('el logger conserva el rol que tenía el actor al registrar la acción', function () {
    $tecnico = User::factory()->create();
    $tecnico->assignRole('TecnicoCampo');

    $this->actingAs($tecnico);

    $registro = AuditLogger::log(action: 'orden.recojo_confirmado', userId: $tecnico->id);

    expect($registro)
        ->not->toBeNull()
        ->actor_role->toBe('Técnico de Campo');
});

test('el visor enlaza una venta a la consulta permitida para gerencia', function () {
    $gerente = createGerenteUserForAuditTest();
    $registro = AuditLog::factory()->create([
        'action' => 'venta.creada',
        'auditable_type' => Sale::class,
        'auditable_id' => 123,
    ]);

    $response = $this->actingAs($gerente)
        ->get(route('gerente.auditoria.index', ['current_team' => $gerente->currentTeam]));

    $response->assertInertia(fn ($page) => $page
        ->where('registros.data.0.id', $registro->id)
        ->where('registros.data.0.accion', 'Venta creada')
        ->where('registros.data.0.enlace', [
            'etiqueta' => 'Ver venta',
            'url' => route('gerente.consulta.venta', [
                'current_team' => $gerente->currentTeam,
                'sale' => 123,
            ]),
        ])
    );
});

test('crear una venta genera un registro de auditoría real', function () {
    $vendedor = User::factory()->create();
    $vendedor->assignRole('Vendedor');

    $client = Client::factory()->create();
    $sede = Sede::factory()->almacen()->create();
    $product = Product::factory()->create(['precio_venta' => 100]);
    $unit = InventoryUnit::factory()->create([
        'product_id' => $product->id,
        'sede_almacen_id' => $sede->id,
        'numero_serie' => 'BF-AUDIT-001',
        'estado' => 'disponible',
    ]);

    $sale = app(CreateSale::class)->handle([
        'client_id' => $client->id,
        'sede_id' => $sede->id,
        'fecha' => now()->toDateString(),
        'destino' => 'local_cliente',
        'condicion_pago' => 'contado',
        'comprobante_tipo' => 'factura',
    ], [
        [
            'tipo_linea' => 'unidad_nueva',
            'numero_serie' => $unit->numero_serie,
            'product_id' => $product->id,
            'cantidad' => 1,
            'precio_unitario' => 100,
            'descuento' => 0,
        ],
    ], $vendedor->id);

    expect(AuditLog::where('action', 'venta.creada')
        ->where('auditable_type', $sale->getMorphClass())
        ->where('auditable_id', $sale->id)
        ->exists())->toBeTrue();
});
