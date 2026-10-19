<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Porcentaje de detracción de un código del catálogo 54 durante su vigencia.
 *
 * @property int $id
 * @property string $codigo
 * @property string $porcentaje
 * @property Carbon $vigente_desde
 * @property Carbon|null $vigente_hasta
 */
class SunatDetraccionRegla extends Model
{
    protected $table = 'sunat_detraccion_reglas';

    public $timestamps = false;

    protected function casts(): array
    {
        return ['porcentaje' => 'decimal:2', 'vigente_desde' => 'date', 'vigente_hasta' => 'date'];
    }
}
