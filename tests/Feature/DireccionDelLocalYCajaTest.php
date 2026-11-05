<?php

use App\Models\CashRegister;
use App\Models\Client;
use App\Models\ClientSite;
use App\Models\Quote;
use App\Models\Sale;
use App\Models\User;
use App\Services\Billing\GuiaRemisionPrefill;
use Illuminate\Validation\ValidationException;

test('el comprobante sale con la direccion del local solo si se pidio', function () {
    $client = Client::factory()->create(['direccion_fiscal' => 'Av. España 100, Trujillo']);
    ClientSite::factory()->create(['client_id' => $client->id, 'nombre' => 'Chacarero', 'direccion' => 'Calle Chacarero 22, Trujillo']);

    $sale = Sale::factory()->create(['client_id' => $client->id, 'referencia' => 'SEDE: Chacarero', 'direccion_del_local' => false]);
    expect($sale->direccionComprobante())->toBe('Av. España 100, Trujillo');

    $sale->update(['direccion_del_local' => true]);
    expect($sale->fresh()->direccionComprobante())->toBe('Calle Chacarero 22, Trujillo');

    // Sin local en la referencia (una placa) queda la fiscal aunque se haya marcado.
    $placa = Sale::factory()->create(['client_id' => $client->id, 'referencia' => 'PLACA: ABC-123', 'direccion_del_local' => true]);
    expect($placa->direccionComprobante())->toBe('Av. España 100, Trujillo');

    $quote = Quote::factory()->create(['client_id' => $client->id, 'referencia' => 'SEDE: Chacarero', 'direccion_del_local' => true]);
    expect($quote->direccionComprobante())->toBe('Calle Chacarero 22, Trujillo');
});

test('la guia de una venta llega al local atendido', function () {
    $client = Client::factory()->create(['direccion_fiscal' => 'Av. España 100, Trujillo']);
    ClientSite::factory()->create(['client_id' => $client->id, 'nombre' => 'Chacarero', 'direccion' => 'Calle Chacarero 22, Trujillo']);
    $sale = Sale::factory()->create(['client_id' => $client->id, 'referencia' => 'SEDE: Chacarero']);

    expect(app(GuiaRemisionPrefill::class)->desdeVenta($sale)['llegada_direccion'])->toBe('Calle Chacarero 22, Trujillo');
});

test('una caja abierta desde ayer no recibe ventas hasta cerrarla', function () {
    $vendedor = User::factory()->create();
    $turno = CashRegister::factory()->create(['vendedor_id' => $vendedor->id, 'estado' => 'abierto', 'fecha_apertura' => now()->subDay()]);

    expect(fn () => CashRegister::exigirAbiertaParaVender($vendedor->id))
        ->toThrow(ValidationException::class, 'Tu caja sigue abierta desde el '.$turno->fecha_apertura->format('d/m/Y'));

    $turno->update(['fecha_apertura' => now()]);
    CashRegister::exigirAbiertaParaVender($vendedor->id);
    expect(true)->toBeTrue();
});
