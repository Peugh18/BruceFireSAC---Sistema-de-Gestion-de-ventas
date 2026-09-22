<?php

use App\Actions\Sales\CreateSale;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\InventoryUnit;
use App\Models\Product;
use App\Models\Sede;
use App\Models\User;
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
        ->where('registros.data', fn ($registros) => collect($registros)->every(fn ($r) => $r['accion'] === 'stock.ajuste'))
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
