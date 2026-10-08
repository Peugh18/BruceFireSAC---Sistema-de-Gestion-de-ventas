<?php

namespace App\Actions\Billing;

use App\Actions\Sales\RevertSale;
use App\Models\Sale;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AnularVentaPorEnviar
{
    public function __construct(
        protected EmitElectronicDocument $emitElectronicDocument,
        protected RevertSale $revertSale,
    ) {}

    /**
     * Anula una venta cuyo comprobante todavía no se envió a SUNAT (por
     * ejemplo, se escaneó el equipo equivocado): el número vuelve a la serie,
     * las unidades regresan al stock y no hace falta nota de crédito.
     */
    public function handle(Sale $sale): Sale
    {
        return DB::transaction(function () use ($sale) {
            // Bloqueada y releída: la venta pudo anularse y el comprobante
            // pudo empezar a enviarse a SUNAT desde que se cargó la pantalla
            // (M4). `descartarPorEnviar` toma además el bloqueo del envío.
            $sale = Sale::query()->lockForUpdate()->findOrFail($sale->id);

            $documento = $sale->electronicDocuments()
                ->whereIn('tipo', ['factura', 'boleta'])
                ->latest('id')
                ->first();

            if ($sale->estado !== 'confirmada' || ! $documento?->estaPorEnviar() || $documento->intento_envio_at !== null) {
                throw ValidationException::withMessages([
                    'comprobante' => 'Solo se anula sin nota de crédito una venta cuyo comprobante aún no se envió a SUNAT.',
                ]);
            }

            $this->emitElectronicDocument->descartarPorEnviar($documento);

            return $this->revertSale->handle($sale, 'antes de enviar el comprobante a SUNAT');
        });
    }
}
