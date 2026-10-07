<?php

use App\Models\CashRegister;
use App\Models\Client;
use App\Models\Quote;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Database\QueryException;

test('la base de datos no deja borrar un usuario con ventas, cajas o cotizaciones', function () {
    $vendedor = User::factory()->create();
    $sale = Sale::factory()->create(['vendedor_id' => $vendedor->id]);
    $caja = CashRegister::factory()->create(['vendedor_id' => $vendedor->id]);
    $quote = Quote::factory()->create(['vendedor_id' => $vendedor->id]);

    expect(fn () => $vendedor->delete())->toThrow(QueryException::class)
        ->and($sale->fresh())->not->toBeNull()
        ->and($caja->fresh())->not->toBeNull()
        ->and($quote->fresh())->not->toBeNull();
});

test('la base de datos no deja borrar un cliente con ventas o cotizaciones', function () {
    $cliente = Client::factory()->create();
    $sale = Sale::factory()->create(['client_id' => $cliente->id]);
    $quote = Quote::factory()->create(['client_id' => $cliente->id]);

    expect(fn () => $cliente->delete())->toThrow(QueryException::class)
        ->and($sale->fresh())->not->toBeNull()
        ->and($quote->fresh())->not->toBeNull();
});
