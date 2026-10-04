<?php

namespace App\Actions\Sales;

use App\Models\Client;
use App\Models\Sale;
use Illuminate\Validation\ValidationException;

class ValidarComprobanteCliente
{
    /**
     * Reglas de SUNAT sobre a quién se le emite cada comprobante: la factura
     * exige RUC (con DNI el error es el 2800) y la boleta sin identificar al
     * cliente solo se admite hasta S/ 700.00. Con $exigirRucHabido (al emitir)
     * además se pide que el RUC esté Activo y Habido y, en factura, que el
     * cliente tenga su dirección fiscal para imprimirla.
     */
    public function handle(Client $client, string $comprobante, float $total, bool $exigirRucHabido = false): void
    {
        if ($comprobante === 'factura' && ! $client->tieneRuc()) {
            throw ValidationException::withMessages([
                'comprobante_tipo' => 'La factura solo se emite a clientes con RUC. A un cliente con DNI o a CLIENTES VARIOS se le emite boleta.',
            ]);
        }

        if ($comprobante === 'boleta' && $client->esClientesVarios() && $total > Sale::LIMITE_BOLETA_SIN_IDENTIFICAR) {
            throw ValidationException::withMessages([
                'client_id' => 'Una boleta a CLIENTES VARIOS no puede superar S/ '.number_format(Sale::LIMITE_BOLETA_SIN_IDENTIFICAR, 2).'. Registra el DNI del cliente.',
            ]);
        }

        if ($exigirRucHabido && $comprobante === 'factura' && $client->direccionImprimible() === null) {
            throw ValidationException::withMessages([
                'client_id' => "Falta la dirección fiscal de {$client->razon_social}: la factura la necesita. Complétala en la venta o en la ficha del cliente.",
            ]);
        }

        if ($exigirRucHabido && $client->tieneRuc()
            && ($client->estado_contribuyente !== 'ACTIVO' || $client->condicion_domicilio !== 'HABIDO')) {
            throw ValidationException::withMessages([
                'client_id' => "No se puede facturar a {$client->razon_social}: su RUC no está Activo y Habido ante SUNAT (estado: {$client->estado_contribuyente}, domicilio: {$client->condicion_domicilio}).",
            ]);
        }
    }
}
