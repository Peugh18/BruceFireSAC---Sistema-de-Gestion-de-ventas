<?php

use App\Models\Client;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->vendedor = User::factory()->create();
    $this->vendedor->assignRole('Vendedor');
});

function compraAnterior(string $documento, string $nombre, string $fecha, float $total): void
{
    DB::table('ml_clientes_historicos')->insertOrIgnore(['documento' => $documento, 'nombre' => $nombre]);
    $producto = DB::table('ml_productos_historicos')->where('nombre', 'RECARGA PQS')->value('id')
        ?? DB::table('ml_productos_historicos')->insertGetId(['nombre' => 'RECARGA PQS', 'categoria' => 'recarga']);
    $comprobante = 'F001-'.fake()->unique()->numerify('####');
    DB::table('ml_comprobantes_historicos')->insert(['comprobante' => $comprobante, 'tipo_doc' => 'F', 'fecha' => $fecha, 'documento_cliente' => $documento, 'archivo_origen' => 'prueba']);
    DB::table('ml_lineas_historicas')->insert(['comprobante' => $comprobante, 'ml_producto_id' => $producto, 'cantidad' => 1, 'total' => $total]);
}

test('la lista de clientes antiguos ordena por la compra mas reciente y omite a los ya registrados', function () {
    compraAnterior('20111111111', 'GRIFO ANTIGUO', '2024-01-10', 100);
    compraAnterior('20222222222', 'COLEGIO RECIENTE', '2025-06-01', 80);
    compraAnterior('20222222222', 'COLEGIO RECIENTE', '2025-03-01', 40);
    compraAnterior('20333333333', 'YA REGISTRADO', '2025-08-01', 500);
    Client::factory()->create(['numero_documento' => '20333333333']);

    $this->actingAs($this->vendedor)
        ->get(route('vendedor.clientes.index', ['current_team' => $this->vendedor->currentTeam, 'vista' => 'por-registrar']))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('porRegistrar.total', 2)
            ->has('porRegistrar.clientes.data', 2)
            ->where('porRegistrar.clientes.data.0.documento', '20222222222')
            ->where('porRegistrar.clientes.data.0.compras', 2)
            ->where('porRegistrar.clientes.data.0.ultima_compra', '2025-06-01')
            ->where('porRegistrar.clientes.data.1.documento', '20111111111'));
});
