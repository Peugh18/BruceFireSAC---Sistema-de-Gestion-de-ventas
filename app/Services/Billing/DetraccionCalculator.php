<?php

namespace App\Services\Billing;

use App\Models\Deficiency;
use App\Models\InventoryMovement;
use App\Models\Sale;
use App\Models\SaleItem;
use Illuminate\Validation\ValidationException;

class DetraccionCalculator
{
    /** @return array{aplica:bool,monto:float,codigo_bien:string} */
    public function paraVenta(Sale $sale, ?string $tipoComprobante = null): array
    {
        $sinDetraccion = ['aplica' => false, 'monto' => 0.0, 'codigo_bien' => ''];
        if (($tipoComprobante ?? $sale->comprobante_tipo) !== 'factura') {
            return $sinDetraccion;
        }

        if ($sale->estado !== 'borrador' && $sale->codigo_detraccion_snapshot !== null && $sale->monto_detraccion_snapshot !== null) {
            return [
                'aplica' => (float) $sale->monto_detraccion_snapshot > 0,
                'monto' => (float) $sale->monto_detraccion_snapshot,
                'codigo_bien' => $sale->codigo_detraccion_snapshot,
            ];
        }

        if ((float) $sale->total <= (float) config('billing.detraccion.monto_minimo')) {
            return $sinDetraccion;
        }

        $sale->loadMissing('items.service');
        $servicios = $sale->items->filter(fn (SaleItem $item) => $item->esServicio() && ($item->tipo_afectacion_igv ?? $item->service->tipo_afectacion_igv ?? '10') === '10');
        if ($servicios->isEmpty()) {
            return $sinDetraccion;
        }

        $codigos = $servicios->map(function (SaleItem $item): string {
            $codigo = $item->service?->codigoDetraccion();
            if (! in_array($codigo, ['020', '022', '037'], true)) {
                throw ValidationException::withMessages(['items' => 'Revisa la clasificación de detracción del servicio '.($item->service->nombre ?? 'sin catálogo').' en el catálogo antes de emitir esta factura.']);
            }

            return $codigo;
        })->unique()->values();

        if ($codigos->count() > 1) {
            throw ValidationException::withMessages(['items' => 'Esta factura combina servicios con códigos distintos o conceptos no sujetos a detracción. Separa los conceptos por clasificación; este emisor admite un solo código de detracción por comprobante.']);
        }

        // El repuesto o producto que se instala dentro del servicio es parte del
        // servicio (va en la misma factura y se detrae sobre el total). El
        // producto suelto que no se instala va en factura aparte.
        $sueltas = $sale->items->reject(fn (SaleItem $item) => $this->esParteDelServicio($item, $sale));
        if ($sueltas->isNotEmpty()) {
            throw ValidationException::withMessages([
                'items' => 'Este producto no es parte del servicio: emítelo en una factura aparte. La detracción del servicio se calcula sobre el total del servicio con su repuesto instalado, no sobre productos vendidos sueltos.',
            ]);
        }

        return $this->calcular((float) $sale->total, true, (string) $codigos->first());
    }

    /**
     * Una línea es parte del servicio cuando es el propio servicio, el adicional
     * que detectó el técnico en una orden (sale_items.deficiency_id) o el
     * repuesto que se consumió en una orden de servicio de esta misma venta.
     */
    private function esParteDelServicio(SaleItem $item, Sale $sale): bool
    {
        if ($item->esServicio()) {
            return true;
        }

        if ($item->deficiency_id !== null) {
            return true;
        }

        return $this->esRepuestoConsumidoEnLaVenta($item, $sale);
    }

    /**
     * ¿El producto de esta línea salió de Almacén como repuesto instalado en una
     * orden de servicio de esta venta?
     */
    private function esRepuestoConsumidoEnLaVenta(SaleItem $item, Sale $sale): bool
    {
        if ($item->product_id === null) {
            return false;
        }

        $deficiencias = Deficiency::query()
            ->whereHas('serviceOrder', fn ($q) => $q->where('sale_id', $sale->id))
            ->pluck('id');

        if ($deficiencias->isEmpty()) {
            return false;
        }

        return InventoryMovement::query()
            ->where('tipo', 'salida_servicio')
            ->where('product_id', $item->product_id)
            ->where('referencia_type', (new Deficiency)->getMorphClass())
            ->whereIn('referencia_id', $deficiencias)
            ->exists();
    }

    /** @return array{aplica:bool,monto:float,codigo_bien:string} */
    public function calcular(float $total, bool $esServicio, ?string $codigoBien = null): array
    {
        $aplica = $esServicio && $total > (float) config('billing.detraccion.monto_minimo');

        return [
            'aplica' => $aplica,
            'monto' => $aplica ? round($total * (float) config('billing.detraccion.tasa'), 2) : 0.0,
            'codigo_bien' => $codigoBien ?? (string) config('billing.detraccion.codigo_bien'),
        ];
    }
}
