<?php

namespace App\Actions\Sales;

use App\Models\Quote;
use App\Models\Sale;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DescartarVentaSinComprobante
{
    public function __construct(
        protected RevertSale $revertSale,
    ) {}

    /**
     * Anula una venta que nunca llegó a SUNAT: un borrador (al crearlo sus
     * unidades ya quedaron reservadas como vendidas) o una nota de venta
     * interna. Las unidades vuelven al stock, sus certificados se anulan y,
     * si venía de una cotización, esta vuelve a quedar aceptada.
     */
    public function handle(Sale $sale, ?string $motivo = null): Sale
    {
        $esBorrador = $sale->estado === 'borrador';
        $esNotaVenta = $sale->estado === 'confirmada' && $sale->esNotaVenta();

        if (! $esBorrador && ! $esNotaVenta) {
            throw ValidationException::withMessages([
                'estado' => $sale->estado === 'anulada'
                    ? 'Esta venta ya está anulada.'
                    : 'Solo se descarta un borrador o se anula una nota de venta. Una factura o boleta se corrige con "Editar comprobante" o con nota de crédito.',
            ]);
        }

        return DB::transaction(function () use ($sale, $esBorrador, $motivo) {
            $this->revertSale->handle($sale, $motivo ?? ($esBorrador ? 'por descarte del borrador' : 'por anulación de la nota de venta'));

            if ($sale->quote_id) {
                Quote::query()->whereKey($sale->quote_id)->where('estado', 'convertida')->update(['estado' => 'aceptada']);
            }

            return $sale->refresh();
        });
    }
}
