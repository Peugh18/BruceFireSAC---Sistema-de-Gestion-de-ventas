<?php

namespace App\Enums;

/**
 * Agente extintor: una sola lista para el catálogo (products.agente, guarda
 * el valor), la unidad del almacén y el equipo del cliente (guarda la
 * etiqueta, que es lo que se imprime en el certificado).
 */
enum EquipmentType: string
{
    case Pqs = 'pqs';
    case PqsBc = 'pqs_bc';
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

    /** @return list<string> */
    public static function etiquetas(): array
    {
        return array_map(fn (self $tipo) => $tipo->etiqueta(), self::cases());
    }

    /** @return list<array{valor: string, etiqueta: string}> */
    public static function opciones(): array
    {
        return array_map(fn (self $tipo) => ['valor' => $tipo->value, 'etiqueta' => $tipo->etiqueta()], self::cases());
    }

    public function etiqueta(): string
    {
        return match ($this) {
            self::Pqs => 'PQS ABC',
            self::PqsBc => 'PQS BC',
            self::Co2 => 'CO2',
            self::Agua => 'Agua presurizada',
            self::Espuma => 'Espuma AFFF',
            self::AcetatoPotasio => 'Acetato de potasio (K)',
            self::AgenteLimpio => 'Agente limpio',
            self::Otro => 'Otro',
        };
    }

    /**
     * Un agente registrado de verdad: uno de la lista o un texto antiguo que
     * lo nombra (por ejemplo "PQS ABC 6 kg"). "No legible" o vacío no sirven.
     */
    public static function esConocido(?string $agente): bool
    {
        $agente = trim((string) $agente);

        return $agente !== '' && (in_array($agente, self::etiquetas(), true) || self::fromDescription($agente) !== self::Otro);
    }

    public static function fromDescription(?string $description): self
    {
        $normalized = mb_strtolower(trim((string) $description));

        return match (true) {
            str_contains($normalized, 'co2') => self::Co2,
            str_contains($normalized, 'pqs') && preg_match('/\bbc\b/', $normalized) === 1 => self::PqsBc,
            str_contains($normalized, 'pqs') => self::Pqs,
            str_contains($normalized, 'acetato') => self::AcetatoPotasio,
            str_contains($normalized, 'espuma') => self::Espuma,
            str_contains($normalized, 'agua') => self::Agua,
            str_contains($normalized, 'limpio') => self::AgenteLimpio,
            default => self::Otro,
        };
    }

    /**
     * Presión de la prueba hidrostática que se sugiere en el formulario
     * (NTP 350.043-1). Null si el técnico debe escribirla.
     */
    public function presionPruebaHidrostatica(): ?string
    {
        return match ($this) {
            self::Co2 => '3000 PSI',
            self::Pqs, self::PqsBc => '600 PSI',
            default => null,
        };
    }

    /**
     * Presión de trabajo nominal del extintor según su agente.
     */
    public function presionTrabajo(): ?string
    {
        return match ($this) {
            self::Co2 => '850 PSI',
            self::Pqs, self::PqsBc => '195 PSI',
            default => null,
        };
    }
}
