<?php

namespace App\Enums;

enum DeficiencyCondition: string
{
    case Danado = 'danado';
    case Vencido = 'vencido';
    case ConFuga = 'con_fuga';
    case Ausente = 'ausente';
    case Desgastado = 'desgastado';
    case Roto = 'roto';
    case Faltante = 'faltante';
    case Corrosion = 'corrosion';
    case Otro = 'otro';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function fromDescription(string $description): self
    {
        $normalized = mb_strtolower(trim($description));

        return match (true) {
            str_contains($normalized, 'fuga') => self::ConFuga,
            str_contains($normalized, 'vencid') => self::Vencido,
            str_contains($normalized, 'ausen') => self::Ausente,
            str_contains($normalized, 'falt') => self::Faltante,
            str_contains($normalized, 'desgast') => self::Desgastado,
            str_contains($normalized, 'rot'), str_contains($normalized, 'fisura') => self::Roto,
            str_contains($normalized, 'corrosi'), str_contains($normalized, 'óxido') => self::Corrosion,
            str_contains($normalized, 'dañ') => self::Danado,
            default => self::Otro,
        };
    }
}
