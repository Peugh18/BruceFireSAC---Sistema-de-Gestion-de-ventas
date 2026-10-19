<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Código del catálogo 25 de SUNAT (UNSPSC): 8 dígitos en cuatro niveles
 * (segmento, familia, clase y producto). Va en cada línea del XML.
 *
 * @property string $codigo
 * @property string $descripcion
 * @property string $clase
 */
class SunatCodigoProducto extends Model
{
    protected $table = 'sunat_codigos_producto';

    protected $primaryKey = 'codigo';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = ['codigo', 'descripcion', 'clase'];

    /**
     * Por código (empieza con lo escrito) o por palabras: cada una debe estar
     * en la descripción o en la clase. UNSPSC separa por segmento: los bienes
     * van del 10 al 60 y los servicios del 70 al 94.
     *
     * @param  'producto'|'servicio'|null  $tipo
     * @return Collection<int, self>
     */
    public static function buscar(string $texto, int $limite = 20, ?string $tipo = null): Collection
    {
        $texto = trim($texto);
        $consulta = self::query()->orderBy('codigo')->limit($limite)
            ->when($tipo === 'servicio', fn (Builder $q) => $q->whereBetween('codigo', ['70000000', '94999999']))
            ->when($tipo === 'producto', fn (Builder $q) => $q->whereBetween('codigo', ['10000000', '60999999']));

        if (ctype_digit($texto)) {
            return $consulta->where('codigo', 'like', $texto.'%')->get();
        }

        foreach (preg_split('/\s+/', $texto) ?: [] as $palabra) {
            // Por la raíz: "extintor" también encuentra "extinguidores" y
            // "mantenimiento", "mantener".
            $palabra = mb_substr($palabra, 0, 5);
            if (mb_strlen($palabra) >= 2) {
                $consulta->where(fn (Builder $q) => $q->where('descripcion', 'like', '%'.$palabra.'%')->orWhere('clase', 'like', '%'.$palabra.'%'));
            }
        }

        return $consulta->get();
    }

    /** El catálogo está cargado: recién entonces se valida contra él. */
    public static function cargado(): bool
    {
        return once(fn (): bool => self::query()->exists());
    }
}
