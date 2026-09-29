<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Producto del sistema anterior con su categoría limpia (solo para la
 * predicción).
 *
 * @property int $id
 * @property string $nombre
 * @property string $categoria
 */
class MlProductoHistorico extends Model
{
    protected $table = 'ml_productos_historicos';

    public $timestamps = false;

    protected $guarded = ['id'];
}
