<?php

namespace App\Models;

use Database\Factories\InventoryUnitFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Unidad física serializada de un CatalogItem (ej. un extintor concreto con
 * su propio número de serie). Vive en un almacén (sedes.tipo IN (almacen,
 * mixta)) hasta que se vende y pasa a ser un Equipment del cliente.
 *
 * @property int $id
 * @property int $catalog_item_id
 * @property int $sede_almacen_id
 * @property string $numero_serie
 * @property string $estado
 * @property Carbon $fecha_ingreso
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read CatalogItem $catalogItem
 * @property-read Sede $sedeAlmacen
 */
#[Fillable(['catalog_item_id', 'sede_almacen_id', 'numero_serie', 'estado', 'fecha_ingreso'])]
class InventoryUnit extends Model
{
    /** @use HasFactory<InventoryUnitFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'fecha_ingreso' => 'date',
        ];
    }

    public function catalogItem(): BelongsTo
    {
        return $this->belongsTo(CatalogItem::class);
    }

    public function sedeAlmacen(): BelongsTo
    {
        return $this->belongsTo(Sede::class, 'sede_almacen_id');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }

    public function estaDisponible(): bool
    {
        return $this->estado === 'disponible';
    }
}
