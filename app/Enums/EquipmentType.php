<?php

namespace App\Enums;

use App\Models\Agente;

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

    /**
     * Agentes que se ofrecen en los formularios: los de la tabla `agentes`
     * (se administran desde el sistema). Sin tabla, los de siempre.
     *
     * @return list<string>
     */
    public static function etiquetas(): array
    {
        $activos = Agente::nombresActivos();

        return $activos !== [] ? $activos : array_map(fn (self $tipo) => $tipo->etiqueta(), self::cases());
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

        return $agente !== '' && (Agente::tipoBaseDe($agente) !== null || in_array($agente, self::etiquetas(), true) || self::fromDescription($agente) !== self::Otro);
    }

    /**
     * Tipo de un agente guardado en un producto o servicio: el nombre de la
     * lista administrable o un valor antiguo del enum ("pqs"). Sin agente,
     * se deduce del respaldo (por ejemplo, el nombre del servicio).
     */
    public static function deAgente(?string $agente, ?string $respaldo = null): self
    {
        $agente = trim((string) $agente);

        return self::tryFrom($agente) ?? self::fromDescription($agente !== '' ? $agente : $respaldo);
    }

    /** Nombre legible del agente guardado: el de la lista, o la etiqueta de un valor antiguo. */
    public static function nombreDe(?string $agente): ?string
    {
        $agente = trim((string) $agente);

        return $agente === '' ? null : (self::tryFrom($agente)?->etiqueta() ?? $agente);
    }

    public static function fromDescription(?string $description): self
    {
        $registrado = Agente::tipoBaseDe($description);
        if ($registrado !== null) {
            return $registrado;
        }

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
