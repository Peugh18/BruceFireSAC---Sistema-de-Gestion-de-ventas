<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Cliente del sistema anterior (solo para la predicción). Se une al cliente
 * del sistema por su número de documento, sin llave foránea: muchos todavía
 * no están registrados.
 *
 * @property string $documento
 * @property string $nombre
 */
class MlClienteHistorico extends Model
{
    protected $table = 'ml_clientes_historicos';

    protected $primaryKey = 'documento';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $guarded = [];

    /**
     * @return HasMany<MlComprobanteHistorico, $this>
     */
    public function comprobantes(): HasMany
    {
        return $this->hasMany(MlComprobanteHistorico::class, 'documento_cliente', 'documento');
    }
}
