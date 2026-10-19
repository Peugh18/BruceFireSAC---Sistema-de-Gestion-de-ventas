<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

/**
 * Marca de extintor de la lista del sistema. El nombre es lo que se guarda en
 * el extintor; quitarla solo la esconde de los formularios.
 *
 * @property int $id
 * @property string $nombre
 * @property bool $activo
 */
#[Fillable(['nombre', 'activo'])]
class Marca extends Model
{
    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }

    /**
     * Nombres que se ofrecen en los formularios.
     *
     * @return list<string>
     */
    public static function nombresActivos(): array
    {
        return once(fn (): array => Schema::hasTable('marcas')
            ? array_values(self::query()->where('activo', true)->orderBy('nombre')->pluck('nombre')->all())
            : []);
    }
}
