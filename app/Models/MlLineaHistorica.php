<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Línea de una factura o boleta del sistema anterior (solo para la
 * predicción).
 *
 * @property int $id
 * @property string $comprobante
 * @property int $ml_producto_id
 * @property string $cantidad
 * @property string $total
 * @property-read MlComprobanteHistorico $comprobanteHistorico
 * @property-read MlProductoHistorico $producto
 */
class MlLineaHistorica extends Model
{
    protected $table = 'ml_lineas_historicas';

    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'cantidad' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<MlComprobanteHistorico, $this>
     */
    public function comprobanteHistorico(): BelongsTo
    {
        return $this->belongsTo(MlComprobanteHistorico::class, 'comprobante', 'comprobante');
    }

    /**
     * @return BelongsTo<MlProductoHistorico, $this>
     */
    public function producto(): BelongsTo
    {
        return $this->belongsTo(MlProductoHistorico::class, 'ml_producto_id');
    }
}
