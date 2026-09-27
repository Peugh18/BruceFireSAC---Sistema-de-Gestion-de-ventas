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

        $quote->update(['estado' => $estado]);

        return $quote;
    }

    public function convertToSale(Quote $quote): Quote
    {
        if (! in_array($quote->estado, ['borrador', 'emitida', 'enviada', 'pendiente', 'aceptada'], true)) {
            throw ValidationException::withMessages([
                'quote_id' => "La cotización {$quote->numero} no se puede pasar a venta porque está {$quote->estado}.",
            ]);
        }

        if ($quote->estado !== 'aceptada') {
            $quote->update(['estado' => 'aceptada']);
        }

        return $this->handle($quote, 'convertida');
    }
}
