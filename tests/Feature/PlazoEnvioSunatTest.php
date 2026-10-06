<?php

use App\Models\ElectronicDocument;
use App\Models\Sale;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->travelTo(CarbonImmutable::parse('2026-10-08 10:00'));
});

function comprobantePendiente(string $tipo, string $serie, string $emision, string $estado = 'pendiente', array $extra = []): ElectronicDocument
{
    return ElectronicDocument::factory()->create([
        'tipo' => $tipo,
        'serie' => $serie,
        'fecha_emision' => CarbonImmutable::parse($emision),
        'sunat_estado' => $estado,
        ...$extra,
    ]);
}

test('la factura y sus notas vencen 3 días calendario después del día de emisión', function (string $tipo, string $serie) {
    $documento = comprobantePendiente($tipo, $serie, '2026-10-06 23:30');

    expect($documento->fechaLimiteEnvio()->toDateString())->toBe('2026-10-09')
        ->and($documento->diasRestantesParaEnvio())->toBe(1)
        ->and($documento->estaPorVencerSunat())->toBeTrue();
})->with([
    'factura' => ['factura', 'F001'],
    'nota de crédito de factura' => ['nota_credito', 'FC01'],
    'nota de débito de factura' => ['nota_debito', 'FD01'],
]);

test('la boleta y sus notas tienen 5 días calendario contando el día de emisión', function (string $tipo, string $serie) {
    $documento = comprobantePendiente($tipo, $serie, '2026-10-06 08:00');

    expect($documento->fechaLimiteEnvio()->toDateString())->toBe('2026-10-10')
        ->and($documento->diasRestantesParaEnvio())->toBe(2)
        ->and($documento->estaPorVencerSunat())->toBeFalse();
})->with([
    'boleta' => ['boleta', 'B001'],
    'nota de crédito de boleta' => ['nota_credito', 'BC01'],
]);

test('solo avisa de comprobantes que aún no llegan a SUNAT', function (string $estado, bool $avisa) {
    expect(comprobantePendiente('factura', 'F001', '2026-10-06', $estado)->estaPorVencerSunat())->toBe($avisa);
})->with([
    ['por_enviar', true],
    ['pendiente', true],
    ['excepcion', true],
    ['aceptado', false],
    ['rechazado', false],
]);

test('la consulta cuenta los que vencen hoy o mañana y los ya vencidos igual que el modelo', function () {
    comprobantePendiente('factura', 'F001', '2026-10-05');            // vence hoy (08)
    comprobantePendiente('factura', 'F001', '2026-10-06', 'por_enviar'); // vence mañana
    comprobantePendiente('boleta', 'B001', '2026-10-05', 'excepcion');  // vence el 09: mañana
    comprobantePendiente('boleta', 'B001', '2026-10-08');             // vence el 12: aún no
    comprobantePendiente('factura', 'F001', '2026-10-04');            // venció el 07
    comprobantePendiente('factura', 'F001', '2026-10-01', 'aceptado'); // ya llegó: no cuenta

    expect(ElectronicDocument::query()->porVencerSunat()->count())->toBe(3)
        ->and(ElectronicDocument::query()->vencidosSunat()->count())->toBe(1);
});

test('la página de facturación del vendedor muestra el aviso y filtra la lista', function () {
    $vendedor = vendedorUser();
    $venta = Sale::factory()->create(['vendedor_id' => $vendedor->id, 'estado' => 'confirmada']);
    comprobantePendiente('factura', 'F001', '2026-10-04', extra: ['sale_id' => $venta->id]);
    comprobantePendiente('factura', 'F001', '2026-10-06', extra: ['sale_id' => $venta->id]);
    comprobantePendiente('factura', 'F001', '2026-10-08', extra: ['sale_id' => $venta->id]);

    $this->actingAs($vendedor)
        ->get(route('vendedor.facturacion.index', ['current_team' => $vendedor->currentTeam]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('plazoSunat.por_vencer', 1)
            ->where('plazoSunat.vencidos', 1));

    $this->actingAs($vendedor)
        ->get(route('vendedor.facturacion.index', ['current_team' => $vendedor->currentTeam, 'plazo' => 'vencidos']))
        ->assertInertia(fn (Assert $page) => $page->has('documents.data', 1));
});

test('el dashboard del gerente recibe los comprobantes por vencer y vencidos', function () {
    $gerente = User::factory()->create();
    $gerente->assignRole('Gerente');
    comprobantePendiente('factura', 'F001', '2026-10-05');
    comprobantePendiente('factura', 'F001', '2026-10-01', 'por_enviar');

    $this->actingAs($gerente)
        ->get(route('gerente.dashboard', ['current_team' => $gerente->currentTeam]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('metrics.plazoSunat.por_vencer', 1)
            ->where('metrics.plazoSunat.vencidos', 1));
});
