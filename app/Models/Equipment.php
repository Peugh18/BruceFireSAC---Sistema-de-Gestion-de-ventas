<?php

namespace App\Models;

use Database\Factories\EquipmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $client_id
 * @property int $catalog_item_id
 * @property string $numero_serie
 * @property Carbon $fecha_venta
 * @property string|null $ubicacion_actual
 * @property string $estado
 * @property Carbon|null $proxima_fecha_atencion
 * @property Carbon|null $proxima_prueba_hidrostatica
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Client $client
 * @property-read CatalogItem $catalogItem
 */
#[Fillable([
    'client_id', 'catalog_item_id', 'numero_serie', 'fecha_venta', 'ubicacion_actual',
    'estado', 'proxima_fecha_atencion', 'proxima_prueba_hidrostatica',
])]
class Equipment extends Model
{
    /** @use HasFactory<EquipmentFactory> */
    use HasFactory;

    protected $table = 'equipment';

    protected function casts(): array
    {
        return [
            'fecha_venta' => 'date',
            'proxima_fecha_atencion' => 'date',
            'proxima_prueba_hidrostatica' => 'date',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function catalogItem(): BelongsTo
    {
        return $this->belongsTo(CatalogItem::class);
    }
}
