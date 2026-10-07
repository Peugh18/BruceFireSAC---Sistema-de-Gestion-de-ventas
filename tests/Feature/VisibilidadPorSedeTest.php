<?php

use App\Models\Certificate;
use App\Models\Client;
use App\Models\Equipment;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Sede;
use App\Models\ServiceOrder;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->sedeA = Sede::factory()->mixta()->create();
    $this->sedeB = Sede::factory()->mixta()->create();
    $this->vendedor = User::factory()->create(['sede_id' => $this->sedeA->id]);
    $this->vendedor->assignRole('Vendedor');
    $this->equipo = ['current_team' => $this->vendedor->currentTeam];
});

test('un vendedor no abre la venta, la orden ni el certificado de otra sede', function () {
    $venta = Sale::factory()->create(['sede_id' => $this->sedeB->id]);
    $orden = ServiceOrder::factory()->create(['sede_id' => $this->sedeB->id]);
    $deVenta = Certificate::factory()->create(['sale_id' => $venta->id]);
    $deOrden = Certificate::factory()->create(['service_order_id' => $orden->id]);

    $this->actingAs($this->vendedor);
    $this->get(route('vendedor.ventas.show', [...$this->equipo, 'sale' => $venta]))->assertNotFound();
    $this->get(route('vendedor.ordenes-servicio.show', [...$this->equipo, 'service_order' => $orden]))->assertNotFound();
    $this->get(route('vendedor.certificados.show', [...$this->equipo, 'certificate' => $deVenta]))->assertNotFound();
    $this->get(route('vendedor.certificados.show', [...$this->equipo, 'certificate' => $deOrden]))->assertNotFound();
    $this->get(route('vendedor.certificados.pdf', [...$this->equipo, 'certificate' => $deOrden]))->assertNotFound();
});

test('el certificado de la venta de un compañero de la misma sede tampoco se abre', function () {
    $companero = User::factory()->create(['sede_id' => $this->sedeA->id]);
    $venta = Sale::factory()->create(['sede_id' => $this->sedeA->id, 'vendedor_id' => $companero->id]);
    $certificado = Certificate::factory()->create(['sale_id' => $venta->id]);

    $this->actingAs($this->vendedor)
        ->get(route('vendedor.certificados.show', [...$this->equipo, 'certificate' => $certificado]))
        ->assertNotFound();
});

test('la ficha del cliente no muestra equipos ni certificados ajenos', function () {
    $client = Client::factory()->create();
    $miVenta = Sale::factory()->create(['client_id' => $client->id, 'sede_id' => $this->sedeA->id, 'vendedor_id' => $this->vendedor->id]);
    $ventaAjena = Sale::factory()->create(['client_id' => $client->id, 'sede_id' => $this->sedeB->id]);
    $miEquipo = Equipment::factory()->create(['client_id' => $client->id]);
    $equipoAjeno = Equipment::factory()->create(['client_id' => $client->id]);
    SaleItem::factory()->create(['sale_id' => $miVenta->id, 'equipment_id' => $miEquipo->id]);
    SaleItem::factory()->create(['sale_id' => $ventaAjena->id, 'equipment_id' => $equipoAjeno->id]);
    $miCertificado = Certificate::factory()->create(['client_id' => $client->id, 'sale_id' => $miVenta->id]);
    Certificate::factory()->create(['client_id' => $client->id, 'sale_id' => $ventaAjena->id]);

    $this->actingAs($this->vendedor)
        ->get(route('vendedor.clientes.show', [...$this->equipo, 'client' => $client]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('ventas', 1)
            ->has('extintores', 1)
            ->where('extintores.0.id', $miEquipo->id)
            ->has('certificados', 1)
            ->where('certificados.0.id', $miCertificado->id));
});

test('el gerente ve la ficha completa del cliente', function () {
    $gerente = User::factory()->create(['sede_id' => $this->sedeA->id]);
    $gerente->assignRole('Gerente');
    $client = Client::factory()->create();
    $venta = Sale::factory()->create(['client_id' => $client->id, 'sede_id' => $this->sedeB->id]);
    $equipo = Equipment::factory()->create(['client_id' => $client->id]);
    SaleItem::factory()->create(['sale_id' => $venta->id, 'equipment_id' => $equipo->id]);
    Certificate::factory()->create(['client_id' => $client->id, 'sale_id' => $venta->id]);

    expect(Sale::query()->visiblePara($gerente)->count())->toBe(1)
        ->and(Equipment::query()->visiblePara($gerente)->count())->toBe(1)
        ->and(Certificate::query()->visiblePara($gerente)->count())->toBe(1);
});
