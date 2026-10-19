<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Model;

/**
 * El código interno lo pone el sistema: PREFIJO-0001, PREFIJO-0002…
 * Nadie lo escribe a mano. El modelo define la constante PREFIJO_CODIGO.
 */
trait TieneCodigoCorrelativo
{
    public static function bootTieneCodigoCorrelativo(): void
    {
        static::creating(function (Model $model): void {
            if (blank($model->getAttribute('codigo'))) {
                $model->setAttribute('codigo', static::siguienteCodigo());
            }
        });
    }

    /**
     * ponytail: busca el siguiente libre sin secuencia bloqueada; basta con un
     * solo gerente creando. Si crean varios a la vez, el índice único lo frena.
     */
    public static function siguienteCodigo(): string
    {
        $numero = (int) static::query()->max('id') + 1;

        do {
            $codigo = sprintf('%s-%04d', static::PREFIJO_CODIGO, $numero++);
        } while (static::query()->where('codigo', $codigo)->exists());

        return $codigo;
    }
}
