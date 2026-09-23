<?php

namespace App\Models;

use Database\Factories\ProductFactory;
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
 * @property string|null $categoria
 * @property string|null $descripcion
 * @property string $unidad_medida
 * @property float $precio_venta
 * @property bool $aplica_igv
 * @property bool $serializado
 * @property int|null $stock_minimo
 * @property bool $activo
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, InventoryUnit> $units
 * @property-read Collection<int, InventoryMovement> $movements
 * @property-read Collection<int, Equipment> $equipment
 * @property-read Collection<int, QuoteItem> $quoteItems
 * @property-read Collection<int, SaleItem> $saleItems
 */
#[Fillable([
    'codigo',
    'nombre',
    'categoria',
    'descripcion',
    'unidad_medida',
    'precio_venta',
    'aplica_igv',
    'serializado',
    'stock_minimo',
    'activo',
])]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'precio_venta' => 'decimal:2',
            'aplica_igv' => 'boolean',
            'serializado' => 'boolean',
            'stock_minimo' => 'integer',
            'activo' => 'boolean',
        ];
    }

    public function units(): HasMany
    {
        return $this->hasMany(InventoryUnit::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }

    public function equipment(): HasMany
    {
        return $this->hasMany(Equipment::class);
    }

    public function quoteItems(): HasMany
    {
        return $this->hasMany(QuoteItem::class);
    }

    public function saleItems(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function esServicio(): bool
    {
        return false;
    }
}
