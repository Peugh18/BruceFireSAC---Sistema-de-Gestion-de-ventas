<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Línea de una factura o boleta del sistema anterior, solo para entrenar la
 * predicción de recompra.
 *
 * @property int $id
 * @property Carbon $fecha
 * @property string $tipo_doc
 * @property string $comprobante
 * @property string $documento_cliente
 * @property string $nombre_cliente
 * @property string $categoria
 * @property string $producto_original
 * @property string $cantidad
 * @property string $total
 * @property string $archivo_origen
 */
class MlVentaHistorica extends Model
{
    protected $table = 'ml_ventas_historicas';

    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'cantidad' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }
}
