<?php

use App\Actions\Billing\EmitElectronicDocument;
use App\Contracts\SunatClientInterface;
use App\Models\ElectronicDocument;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Service;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    Storage::fake('local');
    config(['billing.envio_diferido_horas' => 6, 'billing.sunat.cert_path' => base_path('tests/Fixtures/certificates/test-certificate.pem')]);
    $this->travelTo(now()->setDate(2026, 10, 10)->setTime(20, 0));
    $this->mock(SunatClientInterface::class)->shouldNotReceive('send');
});

function ventaParaPlazo(): Sale
{
    $sale = Sale::factory()->create(['estado' => 'confirmada', 'comprobante_tipo' => 'factura', 'subtotal' => 100, 'igv' => 18, 'total' => 118]);
    SaleItem::factory()->forService(Service::factory()->create())->create(['sale_id' => $sale->id, 'precio_unitario' => 118, 'subtotal' => 118]);

    return $sale;
}

test('la revision de una factura anterior nunca queda despues de su ultimo dia de envio', function () {
    $documento = app(EmitElectronicDocument::class)->programar(ventaParaPlazo(), now()->subDays(3));
    expect($documento->fecha_emision->toDateString())->toBe('2026-10-07')
        ->and($documento->enviar_desde->toDateTimeString())->toBe('2026-10-10 20:00:00');
});

test('una fecha de emision ya vencida no reserva otro comprobante', function () {
    expect(fn () => app(EmitElectronicDocument::class)->programar(ventaParaPlazo(), now()->subDays(4)))->toThrow(ValidationException::class, 'plazo');
    expect(ElectronicDocument::count())->toBe(0);
});

test('una factura reciente conserva su revision normal', function () {
    $documento = app(EmitElectronicDocument::class)->programar(ventaParaPlazo());
    expect($documento->enviar_desde->toDateTimeString())->toBe('2026-10-11 02:00:00');
});
