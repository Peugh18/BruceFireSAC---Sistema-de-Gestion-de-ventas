<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Código del catálogo 54 de SUNAT (bien o servicio sujeto a detracción) con
 * su nombre oficial, la norma de donde sale y sus porcentajes por vigencia.
 *
 * @property string $codigo
 * @property string $nombre
 * @property string $fuente
 * @property bool $soportado
 */
class SunatDetraccion extends Model
{
    protected $table = 'sunat_detracciones';

    protected $primaryKey = 'codigo';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected function casts(): array
    {
        return ['soportado' => 'boolean'];
    }

    /** @return HasMany<SunatDetraccionRegla, $this> */
    public function reglas(): HasMany
    {
        return $this->hasMany(SunatDetraccionRegla::class, 'codigo', 'codigo');
    }

    /** Porcentaje vigente en esa fecha; null si el sistema no lo calcula. */
    public function reglaPara(CarbonInterface $fecha): ?SunatDetraccionRegla
    {
        if (! $this->soportado) {
            return null;
        }

        return $this->reglas()
            ->whereDate('vigente_desde', '<=', $fecha)
            ->where(fn ($q) => $q->whereNull('vigente_hasta')->orWhereDate('vigente_hasta', '>=', $fecha))
            ->latest('vigente_desde')
            ->first();
    }
}
