<?php

namespace App\Actions\Cotizaciones;

use App\Models\Deficiency;
use App\Models\Product;
use App\Models\Quote;
use App\Models\QuoteItem;
use App\Models\SaleItem;
use App\Models\Service;
use App\Models\ServiceOrder;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Billing\AfectacionIgv;
use App\Services\Billing\PrecioConIgv;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CotizarDeficiencia
{
    /** @param array<string, mixed> $datos */
    public function handle(Deficiency $deficiency, array $datos, User $user): Quote
    {
        return DB::transaction(function () use ($deficiency, $datos, $user): Quote {
            $orden = ServiceOrder::query()->lockForUpdate()->findOrFail($deficiency->service_order_id);
            $deficiency = Deficiency::query()->lockForUpdate()->findOrFail($deficiency->id);
            abort_if($user->sedeRestringidaId() !== null && (int) $orden->sede_id !== $user->sedeRestringidaId(), 404);
            if (in_array($orden->estado, ['anulada', 'cerrado', 'entregado'], true) || ! in_array($deficiency->estado, ['detectada', 'esperando_autorizacion', 'autorizada'], true)) {
                throw ValidationException::withMessages(['deficiency' => 'Esta deficiencia ya no permite cambiar su cotización.']);
            }
            if (SaleItem::query()->where('deficiency_id', $deficiency->id)->whereHas('sale', fn ($query) => $query->where('estado', '!=', 'anulada'))->exists()) {
                throw ValidationException::withMessages(['deficiency' => 'El adicional ya está en una venta: corrígelo desde esa venta.']);
            }
            $catalogo = ! empty($datos['service_id']) ? Service::query()->whereKey($datos['service_id'])->firstOrFail() : Product::query()->whereKey($datos['product_id'])->firstOrFail();
            if (! $catalogo->activo || ($catalogo instanceof Product && $catalogo->serializado)) {
                throw ValidationException::withMessages(['product_id' => 'Elige un repuesto sin serie o un servicio activo.']);
            }
            $existente = QuoteItem::query()->where('deficiency_id', $deficiency->id)->first();
            $quoteId = $datos['quote_id'] ?? $existente->quote_id ?? ($orden->sale_id === null ? $orden->quote_id : null);
            $quote = $quoteId ? Quote::query()->lockForUpdate()->whereKey($quoteId)->firstOrFail() : null;
            if ($quote && ((int) $quote->client_id !== (int) $orden->client_id || ($quote->sede_id !== null && (int) $quote->sede_id !== (int) $orden->sede_id) || (! in_array($quote->estado, ['borrador', 'emitida', 'enviada', 'aceptada'], true) || $quote->sale()->exists()))) {
                throw ValidationException::withMessages(['quote_id' => 'Elige una cotización vigente del mismo cliente y sede, aún sin venta.']);
            }
            if ($existente && $quote && $existente->quote_id !== $quote->id) {
                throw ValidationException::withMessages(['quote_id' => 'Edita la cotización donde ya está este adicional.']);
            }
            if (! $quote) {
                $base = [];
                if ($orden->sale_id === null && $orden->service) {
                    $base[] = ['service_id' => $orden->service_id, 'cantidad' => max(1, $orden->equipments()->count()), 'precio_unitario' => (float) $orden->service->precio_venta];
                }
                $lineaInicial = ['product_id' => $catalogo instanceof Product ? $catalogo->id : null, 'service_id' => $catalogo instanceof Service ? $catalogo->id : null, 'cantidad' => (int) $datos['cantidad'], 'precio_unitario' => (float) ($datos['precio_unitario'] ?? $catalogo->precio_venta)];
                $quote = app(CreateQuote::class)->handle([
                    'client_id' => $orden->client_id, 'sede_id' => $orden->sede_id, 'vehicle_id' => $orden->vehicle_id,
                    'fecha' => today(), 'vigencia_hasta' => today()->addDays(15), 'condicion_pago_propuesta' => 'Contado',
                    'referencia' => $orden->codigo, 'observaciones' => 'Cotización de trabajos de '.$orden->codigo,
                ], [...$base, $lineaInicial], $user->id);
                $existente = $quote->items()->latest('id')->firstOrFail();
                $existente->update(['deficiency_id' => $deficiency->id]);
                if ($orden->sale_id === null) {
                    $orden->update(['quote_id' => $quote->id]);
                }
            }
            $antes = $existente?->only(['product_id', 'service_id', 'cantidad', 'precio_unitario', 'descuento', 'subtotal']);
            $cantidad = (int) $datos['cantidad'];
            $precio = (float) ($datos['precio_unitario'] ?? $catalogo->precio_venta);
            $linea = $quote->items()->updateOrCreate(['deficiency_id' => $deficiency->id], [
                'product_id' => $catalogo instanceof Product ? $catalogo->id : null, 'service_id' => $catalogo instanceof Service ? $catalogo->id : null,
                'cantidad' => $cantidad, 'precio_unitario' => $precio, 'descuento' => 0,
                'subtotal' => round($cantidad * $precio, 2), 'tipo_afectacion_igv' => AfectacionIgv::codigo($catalogo),
            ]);
            $quote->update(PrecioConIgv::totalesConAfectacion(array_values($quote->items()->get()->map(fn (QuoteItem $item): array => $item->toArray())->all())));
            if ($quote->estado !== 'borrador') {
                $quote->update(['estado' => 'borrador']);
            }
            if ($deficiency->estado === 'autorizada') {
                $deficiency->authorization()->delete();
            }
            $deficiency->update(['estado' => 'esperando_autorizacion', 'requiere_autorizacion' => true]);
            $orden->events()->create(['tipo' => 'otro', 'user_id' => $user->id, 'payload' => [
                'accion' => 'adicional_cotizado', 'deficiency_id' => $deficiency->id, 'quote_id' => $quote->id,
                'antes' => $antes, 'despues' => $linea->only(['product_id', 'service_id', 'cantidad', 'precio_unitario', 'subtotal']),
                'mensaje' => 'Adicional cotizado en '.$quote->numero.'; pendiente de autorización del cliente.',
            ]]);
            AuditLogger::log(action: 'deficiencia.cotizada', entity: $deficiency, oldValues: $antes, newValues: ['quote_id' => $quote->id, 'importe' => (float) $linea->subtotal], userId: $user->id);

            return $quote->refresh();
        }, 3);
    }
}
