<?php

namespace App\Enums;

enum EquipmentType: string
{
    case Pqs = 'pqs';
    case Co2 = 'co2';
    case Agua = 'agua';
    case Espuma = 'espuma';
    case AcetatoPotasio = 'acetato_potasio';
    case AgenteLimpio = 'agente_limpio';
    case Otro = 'otro';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function fromDescription(?string $description): self
    {
        $normalized = mb_strtolower(trim((string) $description));

        return match (true) {
            str_contains($normalized, 'co2') => self::Co2,
            str_contains($normalized, 'pqs') => self::Pqs,
            str_contains($normalized, 'acetato') => self::AcetatoPotasio,
            str_contains($normalized, 'espuma') => self::Espuma,
            str_contains($normalized, 'agua') => self::Agua,
            str_contains($normalized, 'limpio') => self::AgenteLimpio,
            default => self::Otro,
        };
    }
}
