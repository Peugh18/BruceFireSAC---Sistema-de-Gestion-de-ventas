<?php

namespace App\Models;

use Closure;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * @property int $id
 * @property string $codigo
 * @property string|null $codigo_barras
 * @property string $nombre
 * @property string|null $categoria
 * @property string|null $descripcion
 * @property string $unidad_medida
 * @property float $precio_venta
 * @property Carbon|null $igv_revisado_at
 * @property bool $aplica_igv
 * @property string|null $tipo_afectacion_igv
 * @property bool $serializado
 * @property bool $controla_lote
 * @property string|null $unidad_compra
 * @property int $factor_compra
 * @property int|null $stock_minimo
 * @property bool $activo
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, InventoryUnit> $units
 * @property-read Collection<int, InventoryMovement> $movements
 * @property-read Collection<int, Equipment> $equipment
 * @property-read Collection<int, QuoteItem> $quoteItems
 * @property-read Collection<int, SaleItem> $saleItems
 * @property-read Collection<int, ProductLot> $lots
 */
#[Fillable([
    'codigo',
    'codigo_barras',
    'nombre',
    'categoria',
    'descripcion',
    'unidad_medida',
    'precio_venta',
    'aplica_igv', 'igv_revisado_at',
    'tipo_afectacion_igv',
    'serializado',
    'controla_lote',
    'unidad_compra',
    'factor_compra',
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
            'igv_revisado_at' => 'datetime',
            'serializado' => 'boolean',
            'controla_lote' => 'boolean',
            'factor_compra' => 'integer',
            'stock_minimo' => 'integer',
            'activo' => 'boolean',
        ];
    }

    /**
     * Categorías del catálogo: ordenan el catálogo y deciden qué se avisa
     * en Por vencer (los extintores).
     *
     * @var array<string, string>
     */
    public const CATEGORIAS = [
        'extintor' => 'Extintor',
        'epp' => 'EPP (seguridad personal)',
        'senalizacion' => 'Señalización',
        'repuesto' => 'Repuesto / componente',
        'accesorio' => 'Accesorio (gabinete, soporte)',
        'otro' => 'Otro',
    ];

    /**
     * Agrega el stock disponible a la consulta: los que llevan serie cuentan
     * sus unidades disponibles y los que se venden por cantidad (repuestos,
     * EPP) suman su Kardex. Es el mismo criterio de Almacén, así el Gerente
     * y Almacén ven el mismo número. Se lee con stockDisponible().
     *
     * @param  Builder<Product>  $query
     * @param  list<int>|null  $sedeIds  solo esos almacenes (null = todos)
     */
    public function scopeConStock(Builder $query, ?array $sedeIds = null): void
    {
        if ($query->getQuery()->columns === null) {
            $query->select('products.*');
        }

        $query->selectSub(self::unidadesDisponibles($sedeIds), 'stock_unidades')
            ->selectSub(self::saldoKardex($sedeIds), 'stock_kardex');
    }

    /**
     * Productos activos con mínimo definido y stock en o bajo ese mínimo.
     *
     * @param  Builder<Product>  $query
     * @param  list<int>|null  $sedeIds
     */
    public function scopeBajoMinimo(Builder $query, ?array $sedeIds = null): void
    {
        $minimo = DB::raw('products.stock_minimo');

        $query->where('products.activo', true)
            ->whereNotNull('products.stock_minimo')
            ->where('products.stock_minimo', '>', 0)
            ->where(fn (Builder $q) => $q
                ->where(fn (Builder $q) => $q->where('products.serializado', true)->where(self::unidadesDisponibles($sedeIds), '<=', $minimo))
                ->orWhere(fn (Builder $q) => $q->where('products.serializado', false)->where(self::saldoKardex($sedeIds), '<=', $minimo)));
    }

    /**
     * El stock que trajo conStock().
     */
    public function stockDisponible(): int
    {
        return (int) $this->getAttribute($this->serializado ? 'stock_unidades' : 'stock_kardex');
    }

    /**
     * @param  list<int>|null  $sedeIds
     */
    protected static function unidadesDisponibles(?array $sedeIds): Closure
    {
        return fn (QueryBuilder $q) => $q->from('inventory_units')
            ->selectRaw('COUNT(*)')
            ->whereColumn('inventory_units.product_id', 'products.id')
            ->where('inventory_units.estado', 'disponible')
            ->when($sedeIds !== null, fn (QueryBuilder $q) => $q->whereIn('inventory_units.sede_almacen_id', $sedeIds ?? []));
    }

    /**
     * @param  list<int>|null  $sedeIds
     */
    protected static function saldoKardex(?array $sedeIds): Closure
    {
        return fn (QueryBuilder $q) => $q->from('inventory_movements')
            ->selectRaw('COALESCE(SUM(inventory_movements.cantidad), 0)')
            ->whereColumn('inventory_movements.product_id', 'products.id')
            ->when($sedeIds !== null, fn (QueryBuilder $q) => $q->whereIn('inventory_movements.sede_id', $sedeIds ?? []));
    }

    /**
     * @return HasMany<InventoryUnit, $this>
     */
    public function units(): HasMany
    {
        return $this->hasMany(InventoryUnit::class);
    }

    /**
     * @return HasMany<InventoryMovement, $this>
     */
    public function movements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }

    /**
     * @return HasMany<Equipment, $this>
     */
    public function equipment(): HasMany
    {
        return $this->hasMany(Equipment::class);
    }

    /**
     * @return HasMany<QuoteItem, $this>
     */
    public function quoteItems(): HasMany
    {
        return $this->hasMany(QuoteItem::class);
    }

    /**
     * Lotes con vencimiento (EPP y consumibles), por almacén.
     *
     * @return HasMany<ProductLot, $this>
     */
    public function lots(): HasMany
    {
        return $this->hasMany(ProductLot::class);
    }

    /**
     * @return HasMany<SaleItem, $this>
     */
    public function saleItems(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function esServicio(): bool
    {
        return false;
    }
}
