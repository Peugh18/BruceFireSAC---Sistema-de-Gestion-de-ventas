<?php

namespace App\Actions\Cotizaciones;

use App\Models\Deficiency;
use App\Models\Product;
use App\Models\Quote;
use App\Models\QuoteItem;
use App\Models\SaleItem;
use App\Models\Service;
use App\Services\AuditLogger;
use App\Services\Billing\AfectacionIgv;
use App\Services\Billing\PrecioConIgv;
use App\Services\NumeracionInterna;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateQuote
{
    /**
     * @param  array<string, mixed>  $data
     * @param  list<array<string, mixed>>  $items
     */
    public function actualizar(Quote $quote, array $data, array $items, int $userId): Quote
    {
        return DB::transaction(function () use ($quote, $data, $items, $userId): Quote {
            $quote = Quote::query()->lockForUpdate()->findOrFail($quote->id);
            if (! in_array($quote->estado, ['borrador', 'emitida', 'enviada', 'aceptada'], true) || $quote->sale()->exists()) {
                throw ValidationException::withMessages(['quote_id' => 'La cotización ya tiene una venta o está cerrada.']);
            }
            if ((int) $data['client_id'] !== (int) $quote->client_id || (isset($data['sede_id']) && (int) $data['sede_id'] !== (int) $quote->sede_id)) {
                throw ValidationException::withMessages(['client_id' => 'Conserva el cliente y la sede de esta cotización.']);
            }
            $antes = ['cabecera' => $quote->only(['total', 'estado', 'observaciones']), 'items' => $quote->items()->get()->toArray()];
            $vinculados = $quote->items()->whereNotNull('deficiency_id')->get()->keyBy('deficiency_id');
            foreach ($items as $item) {
                if (! empty($item['deficiency_id']) && ! $vinculados->has($item['deficiency_id'])) {
                    throw ValidationException::withMessages(['items' => 'La deficiencia no pertenece a esta cotización.']);
                }
            }
            foreach ($vinculados as $linea) {
                if (SaleItem::query()->where('deficiency_id', $linea->deficiency_id)->whereHas('sale', fn ($query) => $query->where('estado', '!=', 'anulada'))->exists()) {
                    throw ValidationException::withMessages(['items' => 'Un adicional ya está en una venta: corrígelo desde esa venta.']);
                }
                $nueva = collect($items)->firstWhere('deficiency_id', $linea->deficiency_id);
                $cambio = ! $nueva || (int) ($nueva['product_id'] ?? 0) !== (int) $linea->product_id || (int) ($nueva['service_id'] ?? 0) !== (int) $linea->service_id || (int) $nueva['cantidad'] !== (int) $linea->cantidad || abs((float) $nueva['precio_unitario'] - (float) $linea->precio_unitario) > 0.001 || abs((float) ($nueva['descuento'] ?? 0) - (float) $linea->descuento) > 0.001;
                if ($cambio) {
                    $deficiency = Deficiency::query()->lockForUpdate()->findOrFail($linea->deficiency_id);
                    if ($deficiency->estado === 'resuelta' && $nueva && ((int) ($nueva['product_id'] ?? 0) !== (int) $linea->product_id || (int) ($nueva['service_id'] ?? 0) !== (int) $linea->service_id || (int) $nueva['cantidad'] !== (int) $linea->cantidad)) {
                        throw ValidationException::withMessages(['items' => 'El repuesto ya fue instalado: puedes corregir el precio o retirar el cobro, pero no cambiar aquí el producto o cantidad consumidos.']);
                    }
                    $autorizacionAnterior = $deficiency->authorization?->toArray();
                    $deficiency->authorization()->delete();
                    $deficiency->update(['estado' => $nueva ? ($deficiency->estado === 'resuelta' ? 'resuelta' : 'esperando_autorizacion') : 'rechazada']);
                    $deficiency->serviceOrder->events()->create(['tipo' => 'otro', 'user_id' => $userId, 'payload' => ['accion' => 'adicional_corregido', 'deficiency_id' => $deficiency->id, 'autorizacion_anterior' => $autorizacionAnterior, 'mensaje' => $nueva ? 'Importe corregido: confirmar de nuevo con el cliente.' : 'Adicional retirado de la cotización; historial y movimientos físicos conservados.']]);
                }
            }
            $quote->items()->delete();
            foreach ($items as $item) {
                $catalogo = ! empty($item['service_id']) ? Service::query()->whereKey($item['service_id'])->firstOrFail() : Product::query()->whereKey($item['product_id'])->firstOrFail();
                if (! $catalogo->activo) {
                    throw ValidationException::withMessages(['items' => 'Elige productos y servicios activos.']);
                }
                $quote->items()->create([...$item, 'descuento' => $item['descuento'] ?? 0, 'tipo_afectacion_igv' => AfectacionIgv::codigo($catalogo), 'subtotal' => round($item['cantidad'] * $item['precio_unitario'] - ($item['descuento'] ?? 0), 2)]);
            }
            $totales = PrecioConIgv::totalesConAfectacion(array_values($quote->items()->get()->map(fn (QuoteItem $item): array => $item->toArray())->all()));
            $quote->update([...collect($data)->except(['sede_id', 'vendedor_id', 'estado', 'numero'])->all(), ...$totales, 'estado' => 'borrador']);
            AuditLogger::log(action: 'cotizacion.editada', entity: $quote, oldValues: $antes, newValues: ['cabecera' => $quote->only(['total', 'estado', 'observaciones']), 'items' => $quote->items()->get()->toArray()], userId: $userId);

            return $quote->refresh();
        }, 3);
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<array{product_id?:int,service_id?:int,cantidad:int,precio_unitario:float,descuento?:float}>  $items
     */
    public function handle(array $data, array $items, int $vendedorId): Quote
    {
        return DB::transaction(function () use ($data, $items, $vendedorId) {
            // Precios con IGV incluido, igual que en la venta. La afectación se
            // copia del catálogo y se guarda en la línea para que la cotización
            // calcule sus totales con MISMA regla que la venta: si no, una
            // cotización con líneas exoneradas mostraría un IGV inventado y al
            // convertirla en venta los totales no cuadrarían con lo cotizado.
            $lineas = array_map(function (array $item) {
                $descuento = $item['descuento'] ?? 0;

                $catalogo = ! empty($item['service_id']) ? Service::findOrFail((int) $item['service_id']) : Product::findOrFail((int) ($item['product_id'] ?? 0));
                $afectacion = AfectacionIgv::codigo($catalogo);

                return [...$item, 'tipo_afectacion_igv' => $afectacion, 'descuento' => $descuento, 'subtotal' => round(($item['cantidad'] * $item['precio_unitario']) - $descuento, 2)];
            }, $items);

            ['subtotal' => $subtotal, 'igv' => $igv, 'total' => $total] = PrecioConIgv::totalesConAfectacion($lineas);

            $quote = Quote::create([
                ...$data,
                'numero' => app(NumeracionInterna::class)->siguiente('COT', 'quotes', 'numero'),
                'vendedor_id' => $vendedorId,
                'subtotal' => $subtotal,
                'igv' => $igv,
                'total' => $total,
                'estado' => 'borrador',
            ]);

            foreach ($lineas as $linea) {
                $quote->items()->create($linea);
            }

            return $quote->load('items.product', 'items.service');
        });
    }
}
