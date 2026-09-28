<?php

namespace App\Enums;

enum SaleReceiptType: string
{
    case Factura = 'factura';
    case Boleta = 'boleta';
    case NotaVenta = 'nota_venta';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
