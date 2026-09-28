<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Efectivo = 'efectivo';
    case Transferencia = 'transferencia';
    case Yape = 'yape';
    case Plin = 'plin';
    case Pos = 'pos';
    case Deposito = 'deposito';
    case Otro = 'otro';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
