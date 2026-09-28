<?php

namespace App\Enums;

enum EquipmentStatus: string
{
    case Activo = 'activo';
    case EnServicio = 'en_servicio';
    case Operativo = 'operativo';
    case Baja = 'baja';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
