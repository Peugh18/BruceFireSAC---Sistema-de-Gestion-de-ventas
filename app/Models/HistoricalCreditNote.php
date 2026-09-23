<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property Carbon $fecha
 * @property string $serie
 * @property string $nro_documento
 * @property int|null $client_id
 * @property string $cliente_nombre_original
 * @property string $producto_original
 * @property string $producto_normalizado
 * @property int|null $product_id
 * @property int|null $service_id
 * @property float $cantidad
 * @property float $precio_unitario
 * @property float $total
 * @property string $archivo_origen
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Client|null $client
 * @property-read Product|null $product
 * @property-read Service|null $service
 */
#[Fillable([
    'fecha',
    'serie',
    'nro_documento',
    'client_id',
    'cliente_nombre_original',
    'producto_original',
    'producto_normalizado',
    'product_id',
    'service_id',
    'cantidad',
    'precio_unitario',
    'total',
    'archivo_origen',
])]
class HistoricalCreditNote extends Model
{
    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'cantidad' => 'decimal:2',
            'precio_unitario' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
}
