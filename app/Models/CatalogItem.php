<?php

namespace App\Models;

use Database\Factories\CatalogItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $codigo
 * @property string $nombre
 * @property string $tipo
 * @property string $unidad_medida
 * @property float $precio_venta
 * @property bool $aplica_igv
 * @property bool $activo
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, InventoryUnit> $units
 */
#[Fillable(['codigo', 'nombre', 'tipo', 'unidad_medida', 'precio_venta', 'aplica_igv', 'activo'])]
class CatalogItem extends Model
{
    /** @use HasFactory<CatalogItemFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'precio_venta' => 'decimal:2',
            'aplica_igv' => 'boolean',
            'activo' => 'boolean',
        ];
    }

    public function units(): HasMany
    {
        return $this->hasMany(InventoryUnit::class);
    }

    public function esServicio(): bool
    {
        return $this->tipo === 'servicio';
    }
}
