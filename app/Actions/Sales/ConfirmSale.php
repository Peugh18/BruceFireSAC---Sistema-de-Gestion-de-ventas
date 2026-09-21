<?php

namespace App\Actions\Sales;

use App\Actions\Billing\EmitElectronicDocument;
use App\Models\Sale;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ConfirmSale
{
    public function __construct(
        protected EmitElectronicDocument $emitElectronicDocument,
    ) {}

    /**
     * Confirma una venta en borrador: valida que el RUC del cliente esté
     * Activo y Habido (regla dura SUNAT, ver Documento Maestro §78.1) y,
     * si el comprobante es Factura o Boleta, emite el comprobante
     * electrónico. No aplica a Boleta con DNI: las personas naturales no
     * tienen estado de contribuyente.
     */
    public function handle(Sale $sale): Sale
    {
        if ($sale->estado !== 'borrador') {
            throw ValidationException::withMessages([
                'estado' => 'Solo se puede confirmar una venta en estado borrador.',
            ]);
        }

        $sale->loadMissing('client');

        if ($sale->client->tipo_documento === 'ruc') {
            $this->assertRucActivoYHabido($sale);
        }

        return DB::transaction(function () use ($sale) {
            $sale->update(['estado' => 'confirmada']);

            $this->emitElectronicDocument->handle($sale);

            return $sale->refresh();
        });
    }

    protected function assertRucActivoYHabido(Sale $sale): void
    {
        $client = $sale->client;

        if ($client->estado_contribuyente !== 'ACTIVO' || $client->condicion_domicilio !== 'HABIDO') {
            throw ValidationException::withMessages([
                'client_id' => "No se puede facturar a {$client->razon_social}: su RUC no está Activo y Habido ante SUNAT (estado: {$client->estado_contribuyente}, domicilio: {$client->condicion_domicilio}).",
            ]);
        }
    }
}
