<?php

namespace App\Services\Inventory;

use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\ProductLot;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Entradas y salidas de stock de productos sin serie. Si el producto lleva
 * lote (EPP, consumibles), cada movimiento queda en su lote: lo que entra
 * pide lote y vencimiento, y lo que sale se toma primero del stock sin lote
 * (el de antes de activar los lotes) y luego de lo que vence primero, nunca
 * de un lote vencido. Lo que vuelve (una venta anulada) regresa a su lote.
 *
 * Cada salida se toma con bloqueo dentro de la transacción del llamador, así
 * dos salidas a la vez no dejan el stock en negativo.
 */
class StockPorLote
{
    /**
     * Ingresa stock al almacén, en su lote si el producto lleva lote.
     *
     * @param  array<string, mixed>  $movimiento  tipo, observacion, user_id, referencia
     */
    public function ingresar(Product $product, int $sedeId, int $cantidad, array $movimiento, ?string $lote = null, ?string $vencimiento = null, string $campo = 'lote'): InventoryMovement
    {
        $loteId = $product->controla_lote
            ? $this->lote($product, $sedeId, $lote, $vencimiento, $campo)->id
            : null;

        return $this->movimiento($product->id, $sedeId, $cantidad, $loteId, $movimiento);
    }

    /**
     * Ingresa en otro almacén lo que salió con $salida, en un lote con el
     * mismo número y vencimiento (o sin lote, si salió sin lote).
     *
     * @param  array<string, mixed>  $movimiento
     */
    public function ingresarComo(InventoryMovement $salida, int $sedeId, array $movimiento): InventoryMovement
    {
        $origen = $salida->product_lot_id ? $salida->lot : null;
        $loteId = $origen
            ? $this->lote($salida->product, $sedeId, $origen->lote, $origen->fecha_vencimiento?->toDateString())->id
            : null;

        return $this->movimiento($salida->product_id, $sedeId, abs($salida->cantidad), $loteId, $movimiento);
    }

    /**
     * El lote del producto en ese almacén; si no existe, se crea. Un mismo
     * lote del fabricante tiene una sola fecha de vencimiento.
     */
    public function lote(Product $product, int $sedeId, ?string $lote, ?string $vencimiento, string $campo = 'lote'): ProductLot
    {
        $lote = mb_strtoupper(trim((string) $lote));

        if ($lote === '') {
            throw ValidationException::withMessages([
                $campo => "{$product->nombre} lleva lote: escribe el número de lote y su vencimiento.",
            ]);
        }

        $vencimiento = $vencimiento ? Carbon::parse($vencimiento)->toDateString() : null;

        $existente = ProductLot::query()
            ->where('product_id', $product->id)
            ->where('sede_id', $sedeId)
            ->where('lote', $lote)
            ->lockForUpdate()
            ->first();

        if (! $existente) {
            return ProductLot::create([
                'product_id' => $product->id,
                'sede_id' => $sedeId,
                'lote' => $lote,
                'fecha_vencimiento' => $vencimiento,
            ]);
        }

        if ($vencimiento !== null && $existente->fecha_vencimiento === null) {
            $existente->update(['fecha_vencimiento' => $vencimiento]);
        } elseif ($vencimiento !== null && $existente->fecha_vencimiento?->toDateString() !== $vencimiento) {
            throw ValidationException::withMessages([
                $campo => "El lote {$lote} de {$product->nombre} ya está registrado con vencimiento {$existente->fecha_vencimiento?->format('d/m/Y')}.",
            ]);
        }

        return $existente;
    }

    /**
     * Saca stock del almacén. Con $loteId se saca solo de ese lote (por
     * ejemplo, para dar de baja un lote vencido).
     *
     * @param  array<string, mixed>  $movimiento
     * @return list<InventoryMovement>
     */
    public function sacar(Product $product, int $sedeId, int $cantidad, array $movimiento, string $campo = 'cantidad', ?int $loteId = null, string $verbo = 'sacar'): array
    {
        $tramos = $this->tramos($product, $sedeId);
        $vencidos = 0;

        if ($loteId !== null) {
            $tramos = $tramos->filter(fn (array $tramo) => $tramo['lote']?->id === $loteId);
        } elseif ($product->controla_lote) {
            $vencidos = (int) $tramos->filter(fn (array $tramo) => $tramo['lote']?->estaVencido())->sum('saldo');
            $tramos = $tramos->reject(fn (array $tramo) => $tramo['lote']?->estaVencido());
        }

        $disponible = (int) $tramos->sum('saldo');

        if ($disponible < $cantidad) {
            $detalle = $vencidos > 0 ? " vigentes ({$vencidos} vencidos no se venden)" : '';

            throw ValidationException::withMessages([
                $campo => "Stock insuficiente de {$product->nombre}: hay {$disponible}{$detalle} y se quieren {$verbo} {$cantidad}.",
            ]);
        }

        $movimientos = [];
        $falta = $cantidad;

        foreach ($tramos as $tramo) {
            if ($falta === 0) {
                break;
            }

            $toma = min($falta, $tramo['saldo']);
            $movimientos[] = $this->movimiento($product->id, $sedeId, -$toma, $tramo['lote']?->id, $movimiento);
            $falta -= $toma;
        }

        return $movimientos;
    }

    /**
     * Devuelve al almacén, en su mismo lote, lo que salió con una referencia
     * (venta, orden) y todavía no volvió. Si no hay rastro de la salida (un
     * registro antiguo), entra sin lote en $sedeIdSinRastro.
     *
     * @param  array<string, mixed>  $movimiento
     * @return list<InventoryMovement>
     */
    public function devolver(Model $referencia, int $productId, int $cantidad, array $movimiento, ?int $sedeIdSinRastro = null): array
    {
        $pendientes = InventoryMovement::query()
            ->where('referencia_type', $referencia->getMorphClass())
            ->where('referencia_id', $referencia->getKey())
            ->where('product_id', $productId)
            ->lockForUpdate()
            ->selectRaw('product_lot_id, sede_id, SUM(cantidad) as neto')
            ->groupBy('product_lot_id', 'sede_id')
            ->get()
            ->filter(fn ($fila) => (int) $fila->getAttribute('neto') < 0)
            ->sortByDesc(fn ($fila) => (int) $fila->getAttribute('product_lot_id'));

        $movimientos = [];
        $falta = $cantidad;

        foreach ($pendientes as $fila) {
            if ($falta === 0) {
                break;
            }

            $vuelve = min($falta, -(int) $fila->getAttribute('neto'));
            $loteId = $fila->getAttribute('product_lot_id');
            $movimientos[] = $this->movimiento($productId, (int) $fila->getAttribute('sede_id'), $vuelve, $loteId !== null ? (int) $loteId : null, $movimiento);
            $falta -= $vuelve;
        }

        if ($falta > 0 && $sedeIdSinRastro !== null) {
            $movimientos[] = $this->movimiento($productId, $sedeIdSinRastro, $falta, null, $movimiento);
        }

        return $movimientos;
    }

    /**
     * Saldos positivos del producto en el almacén, en el orden en que se
     * venden: primero el stock sin lote, luego por vencimiento (los lotes sin
     * fecha al final).
     *
     * @return Collection<int, array{lote: ProductLot|null, saldo: int}>
     */
    public function tramos(Product $product, int $sedeId): Collection
    {
        $filas = InventoryMovement::query()
            ->where('product_id', $product->id)
            ->where('sede_id', $sedeId)
            ->lockForUpdate()
            ->selectRaw('product_lot_id, SUM(cantidad) as saldo')
            ->groupBy('product_lot_id')
            ->get()
            ->filter(fn ($fila) => (int) $fila->getAttribute('saldo') > 0);

        $lotes = ProductLot::query()
            ->whereIn('id', $filas->pluck('product_lot_id')->filter()->all())
            ->get()
            ->keyBy('id');

        return $filas
            ->map(fn ($fila) => [
                'lote' => $fila->getAttribute('product_lot_id') !== null ? $lotes->get((int) $fila->getAttribute('product_lot_id')) : null,
                'saldo' => (int) $fila->getAttribute('saldo'),
            ])
            ->sortBy(fn (array $tramo) => $tramo['lote'] === null
                ? '0000-00-00'
                : ($tramo['lote']->fecha_vencimiento?->toDateString() ?? '9999-12-31').'|'.str_pad((string) $tramo['lote']->id, 10, '0', STR_PAD_LEFT))
            ->values();
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    protected function movimiento(int $productId, int $sedeId, int $cantidad, ?int $loteId, array $datos): InventoryMovement
    {
        $movement = new InventoryMovement;
        $movement->product_id = $productId;
        $movement->sede_id = $sedeId;
        $movement->product_lot_id = $loteId;
        $movement->cantidad = $cantidad;
        $movement->tipo = (string) $datos['tipo'];
        $movement->user_id = isset($datos['user_id']) ? (int) $datos['user_id'] : auth()->id();
        $movement->observacion = $datos['observacion'] ?? null;
        $movement->referencia_type = $datos['referencia_type'] ?? null;
        $movement->referencia_id = $datos['referencia_id'] ?? null;
        $movement->save();

        return $movement;
    }
}
