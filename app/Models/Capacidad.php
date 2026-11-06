<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

/**
 * Capacidad de extintor de la lista del sistema ("6 kg", "30 lb", "6 L").
 * Quitarla solo la esconde de los formularios.
 *
 * @property int $id
 * @property string $nombre
 * @property bool $activo
 */
#[Fillable(['nombre', 'activo'])]
class Capacidad extends Model
{
    protected $table = 'capacidades';

    private const UNIDADES = ['kg' => 0, 'lb' => 1, 'L' => 2];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }

    /**
     * Las activas, de menor a mayor y agrupadas por unidad (kg, lb, L).
     *
     * @return list<string>
     */
    public static function nombresActivos(): array
    {
        return once(fn (): array => Schema::hasTable('capacidades')
            ? self::ordenar(self::query()->where('activo', true)->pluck('nombre')->all())
            : []);
    }

    /**
     * @param  array<int, string>  $nombres
     * @return list<string>
     */
    public static function ordenar(array $nombres): array
    {
        usort($nombres, function (string $a, string $b): int {
            [$numeroA, $unidadA] = explode(' ', $a) + [1 => ''];
            [$numeroB, $unidadB] = explode(' ', $b) + [1 => ''];

            return [self::UNIDADES[$unidadA] ?? 9, (float) $numeroA] <=> [self::UNIDADES[$unidadB] ?? 9, (float) $numeroB];
        });

        return $nombres;
    }

    /** "30lb", "50 KG", "6 lts" → "30 lb", "50 kg", "6 L"; null si no es una capacidad. */
    public static function normalizar(string $texto): ?string
    {
        if (! preg_match('/^\s*(\d+(?:[.,]\d+)?)\s*(kg|kgs|lb|lbs|l|lt|lts|litros?)\s*$/iu', $texto, $partes)) {
            return null;
        }

        $numero = rtrim(rtrim(str_replace(',', '.', $partes[1]), '0'), '.');
        if (! str_contains(str_replace(',', '.', $partes[1]), '.')) {
            $numero = $partes[1];
        }
        $unidad = match (true) {
            str_starts_with(mb_strtolower($partes[2]), 'k') => 'kg',
            str_starts_with(mb_strtolower($partes[2]), 'lb') => 'lb',
            default => 'L',
        };

        return (float) $numero > 0 ? "{$numero} {$unidad}" : null;
    }
}
