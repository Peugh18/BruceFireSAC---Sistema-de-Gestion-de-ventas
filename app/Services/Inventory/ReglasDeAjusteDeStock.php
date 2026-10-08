<?php

namespace App\Services\Inventory;

use App\Models\InventoryMovement;
use App\Models\InventoryUnit;
use App\Models\Product;
use App\Models\ProductLot;

/**
 * Reglas de un ajuste de stock (§84.10) definidas UNA sola vez: el
 * FormRequest de la pantalla de ajustes y la Action que los aplica las
 * leen de aquí, para que no se desincronicen.
 */
class ReglasDeAjusteDeStock
{
    /**
     * Estado que debe tener la unidad física para que el ajuste proceda:
     * solo se da de baja lo que está en el almacén y solo vuelve lo que se
     * dio de baja (un extintor vendido no se «revive» con un ajuste).
     * Null si el tipo de ajuste no es de los conocidos.
     */
    public static function estadoRequeridoDeLaUnidad(string $tipoAjuste): ?string
    {
        return match ($tipoAjuste) {
            'incremento' => 'baja',
            'decremento' => 'disponible',
            default => null,
        };
    }

    /**
     * Error por el estado de la unidad, o null si el ajuste procede.
     */
    public static function errorDeEstadoDeLaUnidad(string $tipoAjuste, string $estadoActual): ?string
    {
        $estadoRequerido = self::estadoRequeridoDeLaUnidad($tipoAjuste);

        if ($estadoRequerido === null || $estadoActual === $estadoRequerido) {
            return null;
        }

        return $tipoAjuste === 'incremento'
            ? "Solo se puede reingresar una unidad dada de baja (esta está: {$estadoActual})."
            : "Esa unidad ya no está disponible en el almacén (estado: {$estadoActual}).";
    }

    /**
     * Unidad y producto deben ir juntos: la unidad elegida es la que se
     * ajusta. Error si no corresponden, o null si procede.
     */
    public static function errorDeUnidadDeOtroProducto(?InventoryUnit $unit, int $productId): ?string
    {
        if ($unit === null || $unit->product_id === $productId) {
            return null;
        }

        return 'La unidad seleccionada no corresponde al producto elegido.';
    }

    /**
     * Error si la unidad no está ubicada en el almacén del ajuste, o null.
     */
    public static function errorDeUnidadDeOtraSede(?InventoryUnit $unit, int $sedeId): ?string
    {
        if ($unit === null || $unit->sede_almacen_id === $sedeId) {
            return null;
        }

        return 'La unidad seleccionada no se encuentra ubicada en la sede indicada.';
    }

    /**
     * Un extintor nuevo entra con su serie por Recepciones: sumar sin unidad
     * descuadra el Kardex con el stock. Error si el producto serializado se
     * ajusta sin unidad física, o null si procede.
     */
    public static function errorDeProductoSerializadoSinUnidad(?Product $product, string $tipoAjuste): ?string
    {
        if (! $product?->serializado) {
            return null;
        }

        return match ($tipoAjuste) {
            'incremento' => 'Los productos con serie entran por Recepciones (cada unidad con su serie), no por ajuste.',
            'decremento' => 'Para dar de baja stock de un producto serializado, debe seleccionar la unidad física específica.',
            default => null,
        };
    }

    /**
     * Los productos con lote: el ingreso dice su lote y vencimiento. Error
     * si falta el lote, o null si procede.
     */
    public static function errorDeLoteFaltante(?Product $product, string $tipoAjuste, ?string $lote): ?string
    {
        if (! $product?->controla_lote || $tipoAjuste !== 'incremento' || trim((string) $lote) !== '') {
            return null;
        }

        return "{$product->nombre} lleva lote: escribe el número de lote y su vencimiento.";
    }

    /**
     * El lote indicado debe ser de ese producto en ese almacén. Error si no,
     * o null si procede.
     */
    public static function errorDeLoteAjeno(?int $loteId, int $productId, int $sedeId): ?string
    {
        if ($loteId === null) {
            return null;
        }

        $esDelProducto = ProductLot::query()
            ->whereKey($loteId)
            ->where('product_id', $productId)
            ->where('sede_id', $sedeId)
            ->exists();

        return $esDelProducto ? null : 'Ese lote no es de este producto en este almacén.';
    }

    /**
     * No se dan de baja más unidades de las que hay en el almacén. Error si
     * la cantidad supera el saldo, o null si procede.
     */
    public static function errorDeSaldoInsuficiente(?Product $product, string $tipoAjuste, int $cantidad, int $sedeId): ?string
    {
        if (! $product || $product->serializado || $tipoAjuste !== 'decremento') {
            return null;
        }

        $saldo = InventoryMovement::saldo($product->id, $sedeId);

        return $saldo < $cantidad
            ? "Solo hay {$saldo} en este almacén: no se pueden dar de baja {$cantidad}."
            : null;
    }

    /**
     * Sobre una unidad serializada puntual el ajuste es de una sola unidad.
     * Error si la cantidad no es 1, o null si procede.
     */
    public static function errorDeCantidadParaUnidad(?int $unitId, int $cantidad): ?string
    {
        if ($unitId === null || $cantidad === 1) {
            return null;
        }

        return 'Cuando el ajuste es sobre una unidad serializada específica, la cantidad debe ser 1.';
    }
}
