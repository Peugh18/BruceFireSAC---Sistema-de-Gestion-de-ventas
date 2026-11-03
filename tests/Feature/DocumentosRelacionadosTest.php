<?php

use App\Actions\Billing\IssueCreditNote;
use App\Models\DispatchGuide;
use App\Models\ElectronicDocument;
use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('el detalle de la venta conecta su comprobante y sus guias', function () {
    $user = vendedorUser();
    [$sale, $factura] = ventaConFactura();
    $sale->update(['vendedor_id' => $user->id]);
    $user->update(['sede_id' => $sale->sede_id]);
    $guia = DispatchGuide::query()->forceCreate([
        'sale_id' => $sale->id, 'sede_id' => $sale->sede_id, 'user_id' => $user->id, 'serie' => 'T001', 'correlativo' => 7,
        'fecha_emision' => now(), 'fecha_traslado' => today(), 'motivo' => '01', 'modalidad' => '02',
        'destinatario_tipo_doc' => '6', 'destinatario_num_doc' => '20000000000', 'destinatario_nombre' => 'Cliente',
        'partida_ubigeo' => '130101', 'partida_direccion' => 'Origen', 'llegada_ubigeo' => '130101', 'llegada_direccion' => 'Destino', 'peso_bruto' => 6,
    ]);

    $this->actingAs($user)
        ->get(route('vendedor.ventas.show', [$user->currentTeam, $sale]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('relacionados', function ($filas) use ($factura) {
                $numeros = collect($filas)->pluck('numero');

                return $numeros->contains($factura->serie.'-'.str_pad((string) $factura->correlativo, 8, '0', STR_PAD_LEFT))
                    && $numeros->contains('T001-00000007')
                    && collect($filas)->every(fn ($fila) => filled($fila['url']));
            }));

    expect($guia->sale_id)->toBe($sale->id);
});

test('el envio SUNAT muestra que comprobante modifica cada nota', function () {
    $user = vendedorUser();
    [$sale, $factura] = ventaConFactura();
    $sale->update(['vendedor_id' => $user->id]);
    $user->update(['sede_id' => $sale->sede_id]);
    $nota = app(IssueCreditNote::class)->handle($factura, '04', 'Rebaja', 10);
    $nota->update(['created_at' => now()]);

    $this->actingAs($user)
        ->get(route('vendedor.facturacion.index', ['current_team' => $user->currentTeam, 'periodo' => 'todo']))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('documents.data', function ($filas) use ($nota, $factura) {
                $fila = collect($filas)->firstWhere('id', $nota->id);

                return $fila !== null && $fila['modifica'] === 'Factura '.$factura->serie.'-'.str_pad((string) $factura->correlativo, 8, '0', STR_PAD_LEFT);
            }));

    expect(ElectronicDocument::query()->whereKey($nota->id)->exists())->toBeTrue();
});
