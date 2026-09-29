<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Factura o boleta del sistema anterior (solo para la predicción).
 *
 * @property string $comprobante
 * @property string $tipo_doc
 * @property Carbon $fecha
 * @property string $documento_cliente
 * @property string $archivo_origen
 * @property-read MlClienteHistorico $cliente
 * @property-read Collection<int, MlLineaHistorica> $lineas
 */
class MlComprobanteHistorico extends Model
{
    protected $table = 'ml_comprobantes_historicos';

    protected $primaryKey = 'comprobante';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['fecha' => 'date'];
    }

    /**
     * @return BelongsTo<MlClienteHistorico, $this>
     */
    public function cliente(): BelongsTo
    {
        return $this->belongsTo(MlClienteHistorico::class, 'documento_cliente', 'documento');
    }

    /**
     * @return HasMany<MlLineaHistorica, $this>
     */
    public function lineas(): HasMany
    {
        return $this->hasMany(MlLineaHistorica::class, 'comprobante', 'comprobante');
    }
}
