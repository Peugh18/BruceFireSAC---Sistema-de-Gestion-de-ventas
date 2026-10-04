<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Kardex: un registro por cada entrada/salida/ajuste/traslado de stock,
 * ligado opcionalmente a la unidad serializada exacta que se movió.
 *
 * @property int $id
 * @property int|null $inventory_unit_id
 * @property int|null $product_lot_id
 * @property int $product_id
 * @property int $sede_id
 * @property string $tipo
 * @property int $cantidad
 * @property string|null $referencia_type
 * @property int|null $referencia_id
 * @property int|null $user_id
 * @property string|null $observacion
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read InventoryUnit|null $inventoryUnit
 * @property-read ProductLot|null $lot
 * @property-read Product $product
 * @property-read Sede $sede
 * @property-read User|null $user
 */
#[Fillable(['inventory_unit_id', 'product_lot_id', 'product_id', 'sede_id', 'tipo', 'cantidad', 'referencia_type', 'referencia_id', 'user_id', 'observacion'])]
class InventoryMovement extends Model
{
    /**
     * @return BelongsTo<InventoryUnit, $this>
     */
    public function inventoryUnit(): BelongsTo
    {
        return $this->belongsTo(InventoryUnit::class);
    }

    /**
     * @return BelongsTo<ProductLot, $this>
     */
    public function lot(): BelongsTo
    {
        return $this->belongsTo(ProductLot::class, 'product_lot_id');
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
    public function sede(): BelongsTo
    {
        return $this->belongsTo(Sede::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function referencia(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Saldo de un producto sin serie en un almacén (suma de su Kardex). Con
     * $bloquear se toma el saldo dentro de la transacción para que dos
     * salidas a la vez no dejen el stock en negativo.
     */
    public static function saldo(int $productId, int $sedeId, bool $bloquear = false): int
    {
        return (int) self::query()
            ->where('product_id', $productId)
            ->where('sede_id', $sedeId)
            ->when($bloquear, fn ($query) => $query->lockForUpdate())
            ->sum('cantidad');
    }

    /**
     * Corta una salida que dejaría el stock en negativo.
     */
    public static function exigirSaldo(Product $product, int $sedeId, int $cantidad, string $campo = 'cantidad'): void
    {
        $saldo = self::saldo($product->id, $sedeId, bloquear: true);

        if ($saldo < $cantidad) {
            throw ValidationException::withMessages([
                $campo => "Stock insuficiente de {$product->nombre}: hay {$saldo} y se quieren sacar {$cantidad}.",
            ]);
        }
    }
}
