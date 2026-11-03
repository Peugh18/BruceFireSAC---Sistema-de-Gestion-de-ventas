<?php

namespace App\Actions\Cotizaciones;

use App\Models\Quote;
use Illuminate\Validation\ValidationException;

class TransitionQuoteState
{
    public function handle(Quote $quote, string $estado): Quote
    {
        if (! $quote->canTransitionTo($estado)) {
            throw ValidationException::withMessages([
                'estado' => "No se puede pasar la cotización {$quote->numero} de \"{$quote->estado}\" a \"{$estado}\".",
            ]);
        }

        $quote->update([
            'estado' => $estado,
            // X7: el tiempo de cotización termina la primera vez que se emite o envía.
            'emitida_at' => $quote->emitida_at ?? (in_array($estado, ['emitida', 'enviada'], true) ? now() : null),
        ]);

        return $quote;
    }

    /**
     * El cliente acepta: queda quién, por qué canal y cuándo, y su orden de
     * compra si la mandó (campo 54 del Anexo I, R.S. 114-2019; opcional).
     *
     * @param  array{aceptada_por: string, aceptada_canal: string, aceptada_at?: mixed, orden_compra?: ?string, orden_compra_path?: ?string}  $datos
     */
    public function aceptar(Quote $quote, array $datos): Quote
    {
        $this->handle($quote, 'aceptada');

        $quote->update([
            'aceptada_por' => $datos['aceptada_por'],
            'aceptada_canal' => $datos['aceptada_canal'],
            'aceptada_at' => $datos['aceptada_at'] ?? now(),
            'orden_compra' => filled($datos['orden_compra'] ?? null) ? mb_strtoupper(trim($datos['orden_compra'])) : null,
            'orden_compra_path' => $datos['orden_compra_path'] ?? null,
        ]);

        return $quote;
    }

    /**
     * D-10: una cotización pasa a venta solo con la aceptación del cliente
     * registrada; así la venta sabe de dónde viene.
     */
    public function convertToSale(Quote $quote): Quote
    {
        self::exigirAceptada($quote, 'pasar a venta');

        return $this->handle($quote, 'convertida');
    }

    public static function exigirAceptada(Quote $quote, string $accion): void
    {
        if (in_array($quote->estado, ['rechazada', 'vencida', 'anulada', 'convertida'], true)) {
            throw ValidationException::withMessages([
                'quote_id' => "La cotización {$quote->numero} no se puede {$accion} porque está {$quote->estado}.",
            ]);
        }

        if ($quote->estado !== 'aceptada') {
            throw ValidationException::withMessages([
                'quote_id' => "Para {$accion}, registra primero que el cliente aceptó la cotización {$quote->numero}.",
            ]);
        }
    }
}
