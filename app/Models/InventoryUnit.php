<?php

namespace App\Models;

use App\Enums\EquipmentType;
use Database\Factories\InventoryUnitFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Unidad física serializada de un Product (ej. un extintor concreto con
 * su propio número de serie). Vive en un almacén (sedes.tipo IN (almacen,
 * mixta)) hasta que se vende y pasa a ser un Equipment del cliente.
 *
 * @property int $id
 * @property int $product_id
 * @property int $sede_almacen_id
 * @property string $numero_serie
 * @property string|null $capacidad
 * @property string|null $agente
 * @property string|null $serie_fabricante
 * @property string|null $marca
 * @property int|null $anio_fabricacion
 * @property string $estado
 * @property Carbon $fecha_ingreso
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Product $product
 * @property-read Sede $sedeAlmacen
 */
#[Fillable(['product_id', 'sede_almacen_id', 'numero_serie', 'capacidad', 'agente', 'serie_fabricante', 'marca', 'anio_fabricacion', 'estado', 'fecha_ingreso'])]
class InventoryUnit extends Model
{
    /** @use HasFactory<InventoryUnitFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'fecha_ingreso' => 'date',
            'anio_fabricacion' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsTo<Sede, $this>
     */
    public function sedeAlmacen(): BelongsTo
    {
        return $this->belongsTo(Sede::class, 'sede_almacen_id');
    }

    /**
     * @return HasMany<InventoryMovement, $this>
     */
    public function movements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }

    public function estaDisponible(): bool
    {
        return $this->estado === 'disponible';
    }

    public function getCodigoInternoAttribute(): string
    {
        return $this->numero_serie;
    }

    /**
     * Agente que pasa al equipo del cliente (la etiqueta que se imprime):
     * el de la unidad o, si no lo tiene, el de su producto. Null si nadie lo
     * registró; nunca se asume uno (C1).
     */
    public function agenteParaEquipo(): ?string
    {
        return EquipmentType::tryFrom((string) ($this->agente ?? $this->product->agente))?->etiqueta();
    }
}
