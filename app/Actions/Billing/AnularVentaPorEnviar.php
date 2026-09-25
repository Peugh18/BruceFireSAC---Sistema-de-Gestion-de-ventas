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
        $documento = $sale->electronicDocuments()
            ->whereIn('tipo', ['factura', 'boleta'])
            ->latest('id')
            ->first();

        if ($sale->estado !== 'confirmada' || ! $documento?->estaPorEnviar()) {
            throw ValidationException::withMessages([
                'comprobante' => 'Solo se anula sin nota de crédito una venta cuyo comprobante aún no se envió a SUNAT.',
            ]);
        }

        return DB::transaction(function () use ($sale, $documento) {
            $this->emitElectronicDocument->descartarPorEnviar($documento);

            return $this->revertSale->handle($sale, 'antes de enviar el comprobante a SUNAT');
        });
    }
}
