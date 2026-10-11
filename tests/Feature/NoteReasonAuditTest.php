<?php

use App\Actions\Billing\AplicarNotaAlSaldo;
use App\Actions\Billing\IssueCreditNote;
use App\Actions\Billing\NotaPorAprobar;
use App\Models\InventoryMovement;
use App\Services\Billing\DatosEmision;
use App\Services\Billing\GreenterService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Validation\ValidationException;

beforeEach(fn () => $this->seed(RolesAndPermissionsSeeder::class));

test('motivo descripción requiere la descripción corregida por línea', function () {
    [, $original] = ventaConFactura();
    expect(fn () => app(IssueCreditNote::class)->handle($original, '03', 'Corregir texto', 1))
        ->toThrow(ValidationException::class);
});

test('corrección de descripción conserva original y no modifica deuda ni stock', function () {
    [$sale, $original, $unit] = ventaConFactura('credito_30');
    $datos = app(DatosEmision::class)->recuperar($original);
    $nota = app(IssueCreditNote::class)->handle($original, '03', 'Texto correcto', 0, ['lineas' => [['indice' => 0, 'descripcion' => 'EXTINTOR DESCRIPCIÓN CORREGIDA']]]);
    $ubl = app(GreenterService::class)->buildNote($nota);
    expect($ubl->getDetails()[0]->getDescripcion())->toBe('EXTINTOR DESCRIPCIÓN CORREGIDA')
        ->and((float) $ubl->getMtoImpVenta())->toBe(0.0)
        ->and(app(DatosEmision::class)->recuperar($original->fresh()))->toEqual($datos);
    $nota->update(['sunat_estado' => 'aceptado']);
    app(AplicarNotaAlSaldo::class)->handle($nota);
    expect($sale->installments()->sum('monto_acreditado'))->toBe('0.00')
        ->and($unit->fresh()->estado)->toBe('vendido');
});

test('descuento por ítem exige líneas y no acepta reparto arbitrario', function () {
    [, $original] = ventaConFactura();
    expect(fn () => app(IssueCreditNote::class)->handle($original, '05', 'Descuento', 10))->toThrow(ValidationException::class);
});

test('solicitud conserva descuento por línea al aprobar sin alterar inventario', function () {
    [$sale, $original, $unit] = ventaConFactura();
    $payload = ['lineas' => [['indice' => 0, 'importe' => 11.8]]];
    $solicitud = app(NotaPorAprobar::class)->solicitar($original, 'nota_credito', '05', 'Descuento por unidad', 11.8, $sale->vendedor_id, $payload);
    $nota = app(NotaPorAprobar::class)->aprobar($solicitud->fresh(), $sale->vendedor_id);
    expect($nota->nota_datos['opciones']['lineas'])->toBe($payload['lineas'])
        ->and((float) app(GreenterService::class)->buildNote($nota)->getMtoIGV())->toBe(1.8)
        ->and($unit->fresh()->estado)->toBe('vendido');
});

test('devolución parcial debe indicar cantidad y el importe debe coincidir', function () {
    [, $original] = ventaConFactura();
    expect(fn () => app(IssueCreditNote::class)->handle($original, '07', 'Devolución', 10, ['lineas' => [['indice' => 0, 'cantidad' => 1]]]))
        ->toThrow(ValidationException::class);
});

test('anulación exige el total del original y no emite anulaciones parciales', function () {
    [, $original] = ventaConFactura();
    expect(fn () => app(IssueCreditNote::class)->handle($original, '01', 'Anulación parcial inválida', 10))->toThrow(ValidationException::class);
});

test('anulación fiscal sin recepción física explícita conserva unidades vendidas', function () {
    [$sale, $original, $unit] = ventaConFactura();
    $nota = aceptarNota(app(IssueCreditNote::class)->handle($original, '01', 'Anular solo documento', (float) $sale->total, ['devolucion_fisica' => false]));
    app(IssueCreditNote::class)->aplicarSiFueAceptada($nota);
    expect($sale->fresh()->estado)->toBe('anulada')->and($unit->fresh()->estado)->toBe('vendido')
        ->and(InventoryMovement::where('tipo', 'ingreso')->where('referencia_id', $sale->id)->count())->toBe(0);
});

test('recepción física total confirmada reintegra una sola vez', function () {
    [$sale, $original, $unit] = ventaConFactura();
    $nota = aceptarNota(app(IssueCreditNote::class)->handle($original, '06', 'Recepción total', (float) $sale->total, ['devolucion_fisica' => true]));
    app(IssueCreditNote::class)->aplicarSiFueAceptada($nota);
    expect($unit->fresh()->estado)->toBe('disponible')
        ->and(InventoryMovement::where('tipo', 'ingreso')->where('referencia_id', $sale->id)->count())->toBe(1);
});
