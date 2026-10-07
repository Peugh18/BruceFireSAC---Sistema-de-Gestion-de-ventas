<?php

use App\Models\Client;
use App\Models\Quote;
use App\Models\Sale;
use App\Models\Sede;
use App\Models\ServiceOrder;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

it('entrega el contrato completo de la ficha respetando la sede del vendedor', function () {
    $sede = Sede::factory()->almacen()->create();
    $otraSede = Sede::factory()->almacen()->create();
    $vendedor = User::factory()->create(['sede_id' => $sede->id]);
    $vendedor->assignRole('Vendedor');
    $client = Client::factory()->create();
    Quote::factory()->create(['client_id' => $client->id, 'sede_id' => $sede->id, 'vendedor_id' => $vendedor->id]);
    Quote::factory()->create(['client_id' => $client->id, 'sede_id' => $otraSede->id]);
    Sale::factory()->create(['client_id' => $client->id, 'sede_id' => $sede->id, 'vendedor_id' => $vendedor->id]);
    Sale::factory()->create(['client_id' => $client->id, 'sede_id' => $otraSede->id]);
    ServiceOrder::factory()->create(['client_id' => $client->id, 'sede_id' => $sede->id]);
    ServiceOrder::factory()->create(['client_id' => $client->id, 'sede_id' => $otraSede->id]);

    $this->actingAs($vendedor)
        ->get(route('vendedor.clientes.show', ['current_team' => $vendedor->currentTeam, 'client' => $client]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('client')
            ->has('cotizaciones', 1)
            ->has('ventas', 1)
            ->has('extintores')
            ->has('certificados')
            ->has('servicios', 1)
            ->has('cobranzas.cuotas')
            ->has('cobranzas.pagos')
            ->has('historial')
            ->has('sunat.verificado'));
});

it('fuerza una consulta SUNAT y actualiza al cliente sin usar la respuesta local', function () {
    config(['services.apisperu.token' => 'token-prueba']);
    Http::preventStrayRequests();
    Http::fake([
        'dniruc.apisperu.com/api/v1/ruc/*' => Http::response([
            'razonSocial' => 'CLIENTE ACTUALIZADO SAC',
            'direccion' => 'AV. QA 123',
            'estado' => 'ACTIVO',
            'condicion' => 'HABIDO',
        ]),
    ]);

    $vendedor = User::factory()->create();
    $vendedor->assignRole('Vendedor');
    $client = Client::factory()->create([
        'tipo_documento' => 'ruc',
        'numero_documento' => '20999999991',
        'estado_contribuyente' => null,
        'condicion_domicilio' => null,
    ]);

    $this->actingAs($vendedor)
        ->post(route('vendedor.clientes.verificar-sunat', ['current_team' => $vendedor->currentTeam, 'client' => $client]))
        ->assertRedirect();

    expect($client->fresh()->estado_contribuyente)->toBe('ACTIVO')
        ->and($client->fresh()->condicion_domicilio)->toBe('HABIDO')
        ->and($client->fresh()->consultado_at)->not->toBeNull();

    Http::assertSentCount(1);
});

it('resume lo comprado y lo que debe: al contado no queda deuda y la anulada no suma', function () {
    $vendedor = User::factory()->create();
    $vendedor->assignRole('Vendedor');
    $client = Client::factory()->create();

    Sale::factory()->create(['client_id' => $client->id, 'vendedor_id' => $vendedor->id, 'estado' => 'confirmada', 'condicion_pago' => 'contado', 'total' => 460.20, 'fecha' => '2026-09-20']);
    $credito = Sale::factory()->create(['client_id' => $client->id, 'vendedor_id' => $vendedor->id, 'estado' => 'confirmada', 'condicion_pago' => 'credito', 'total' => 100, 'fecha' => '2026-09-25']);
    $credito->installments()->create(['numero_cuota' => 1, 'fecha_vencimiento' => now()->subDay(), 'monto' => 100, 'estado' => 'pendiente']);
    Sale::factory()->create(['client_id' => $client->id, 'vendedor_id' => $vendedor->id, 'estado' => 'anulada', 'condicion_pago' => 'contado', 'total' => 999, 'fecha' => '2026-09-01']);

    $this->actingAs($vendedor)
        ->get(route('vendedor.clientes.show', ['current_team' => $vendedor->currentTeam, 'client' => $client]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('resumen.total_comprado', 560.2)
            ->where('resumen.deuda_pendiente', 100)
            ->where('resumen.cuotas_vencidas', 1)
            ->where('resumen.ultima_compra', '2026-09-25')
            ->where('ventas.0.saldo_pendiente', 100)
            ->where('ventas.1.saldo_pendiente', 0));
});
