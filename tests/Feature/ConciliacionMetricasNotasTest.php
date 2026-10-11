<?php

use App\Models\ElectronicDocument;
use App\Models\Sale;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->gerente = User::factory()->create();
    $this->gerente->assignRole('Gerente');
});

test('reporte y dashboard concilian originales menos NC mas ND aceptadas', function () {
    $venta = Sale::factory()->create(['estado' => 'confirmada', 'comprobante_tipo' => 'factura', 'fecha' => today(), 'total' => 100]);
    foreach ([['nota_credito', 'aceptado', 20], ['nota_debito', 'observado', 5], ['nota_credito', 'pendiente', 70], ['nota_debito', 'rechazado', 30]] as [$tipo, $estado, $importe]) {
        ElectronicDocument::factory()->create(['sale_id' => $venta->id, 'tipo' => $tipo, 'importe' => $importe, 'sunat_estado' => $estado, 'fecha_emision' => now()]);
    }
    $ruta = ['current_team' => $this->gerente->currentTeam];
    $reporte = $this->actingAs($this->gerente)->get(route('gerente.reportes.index', $ruta))->assertOk()->viewData('page')['props']['reporteComercial'];
    $dashboard = $this->get(route('gerente.dashboard', $ruta))->assertOk()->viewData('page')['props']['metrics'];
    expect($reporte['totalVentas'])->toEqual(85)
        ->and($dashboard['ventasDia'])->toEqual(85)
        ->and($dashboard['ventasMes'])->toEqual(85)
        ->and($reporte['ventasOriginales'])->toEqual(100)
        ->and($reporte['notasCredito'])->toEqual(20)
        ->and($reporte['notasDebito'])->toEqual(5)
        ->and($venta->fresh()->total)->toBe('100.00');
});

test('NC de venta anterior se imputa a fecha de nota sin borrar original ni duplicar descuento', function () {
    $venta = Sale::factory()->create(['estado' => 'anulada', 'comprobante_tipo' => 'factura', 'fecha' => today()->subMonths(2), 'total' => 100]);
    ElectronicDocument::factory()->create(['sale_id' => $venta->id, 'tipo' => 'nota_credito', 'importe' => 100, 'sunat_estado' => 'aceptado', 'fecha_emision' => now()]);
    $ruta = ['current_team' => $this->gerente->currentTeam];
    $reporte = $this->actingAs($this->gerente)->get(route('gerente.reportes.index', $ruta))->assertOk()->viewData('page')['props']['reporteComercial'];
    $dashboard = $this->get(route('gerente.dashboard', $ruta))->assertOk()->viewData('page')['props']['metrics'];
    expect($reporte['totalVentas'])->toEqual(-100)
        ->and($dashboard['ventasMes'])->toEqual(-100)
        ->and($reporte['ventasOriginales'])->toEqual(0)
        ->and($reporte['notasCredito'])->toEqual(100)
        ->and($reporte['cantidadVentas'])->toEqual(0);
});

test('filtro de vendedor acota originales y notas del periodo', function () {
    $vendedor = User::factory()->create();
    $propia = Sale::factory()->create(['vendedor_id' => $vendedor->id, 'estado' => 'confirmada', 'fecha' => today(), 'total' => 100]);
    $otra = Sale::factory()->create(['estado' => 'confirmada', 'fecha' => today(), 'total' => 200]);
    foreach ([$propia, $otra] as $venta) {
        ElectronicDocument::factory()->create(['sale_id' => $venta->id, 'tipo' => 'nota_credito', 'importe' => 20, 'sunat_estado' => 'aceptado', 'fecha_emision' => now()]);
    }
    $data = $this->actingAs($this->gerente)->get(route('gerente.reportes.index', ['current_team' => $this->gerente->currentTeam, 'vendedor_id' => $vendedor->id]))->assertOk()->viewData('page')['props']['reporteComercial'];
    expect($data['totalVentas'])->toEqual(80)->and($data['notasCredito'])->toEqual(20)->and($data['ventasOriginales'])->toEqual(100);
});
