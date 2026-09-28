<?php

namespace App\Enums;

enum CertificateFamily: string
{
    case Operatividad = 'operatividad';
    case Instalacion = 'instalacion';
    case Diploma = 'diploma';
    case Externo = 'externo';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
