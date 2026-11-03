<?php

use App\Models\ElectronicDocument;
use App\Services\Billing\DesgloseNota;

/*
| La nota que anula la venta repite los mismos ítems del comprobante que
| modifica (SUNAT, oficio 033-2002: «los mismos requisitos y características»;
| Odoo revierte todas las líneas). Antes salía una sola línea «NC-01».
*/

function boletaConDosLineas(): ElectronicDocument
{
    $original = new ElectronicDocument;
    $original->forceFill(['datos_emision' => [
        'cliente' => ['razon_social' => 'CLIENTES VARIOS'],
        'lineas' => [
            ['codigo' => 'SRV-0001', 'nombre' => 'Recarga y mantenimiento de extintor PQS ABC 6 kg', 'unidad_medida' => 'ZZ',
                'cantidad' => 1, 'precio_unitario' => 35.0, 'valor_unitario' => 29.66, 'base' => 29.66, 'igv' => 5.34,
                'subtotal' => 35.0, 'descuento' => 0.0, 'tipo_afectacion_igv' => '10'],
            ['codigo' => 'PRD-0008', 'nombre' => 'Señal fotoluminiscente 20 × 30 cm', 'unidad_medida' => 'NIU',
                'cantidad' => 2, 'precio_unitario' => 12.8, 'valor_unitario' => 10.85, 'base' => 21.69, 'igv' => 3.91,
                'subtotal' => 25.6, 'descuento' => 0.0, 'tipo_afectacion_igv' => '10'],
        ],
        'totales' => ['gravadas' => 51.35, 'exoneradas' => 0, 'inafectas' => 0, 'igv' => 9.25, 'total' => 60.6],
    ]]);

    return $original;
}

it('al anular, la nota de crédito copia todos los ítems del comprobante', function (string $motivo) {
    $desglose = app(DesgloseNota::class)->calcular(boletaConDosLineas(), 'nota_credito', $motivo, 60.6);

    expect(array_column($desglose['lineas'], 'codigo'))->toBe(['SRV-0001', 'PRD-0008'])
        ->and(array_column($desglose['lineas'], 'nombre'))->toBe(['Recarga y mantenimiento de extintor PQS ABC 6 kg', 'Señal fotoluminiscente 20 × 30 cm'])
        ->and(array_column($desglose['lineas'], 'cantidad'))->toBe([1, 2])
        ->and(round(array_sum(array_column($desglose['lineas'], 'subtotal')), 2))->toBe(60.6);
})->with(['01 anulación' => '01', '02 error en el RUC' => '02', '06 devolución total' => '06']);

it('la anulación del saldo tras otra nota va en una sola línea, sin repartir', function () {
    $desglose = app(DesgloseNota::class)->calcular(boletaConDosLineas(), 'nota_credito', '01', 50.6);

    expect($desglose['lineas'])->toHaveCount(1)
        ->and($desglose['lineas'][0]['subtotal'])->toBe(50.6);
});

it('la nota de débito por intereses sí lleva su propia línea', function () {
    $desglose = app(DesgloseNota::class)->calcular(boletaConDosLineas(), 'nota_debito', '01', 10);

    expect($desglose['lineas'])->toHaveCount(1);
});
