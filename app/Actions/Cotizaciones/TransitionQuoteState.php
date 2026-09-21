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
}
