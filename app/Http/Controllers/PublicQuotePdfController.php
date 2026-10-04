<?php

namespace App\Http\Controllers;

use App\Models\Quote;
use App\Services\Quotes\QuotePdfService;
use Symfony\Component\HttpFoundation\Response;

/**
 * El cliente abre su cotización desde el enlace de WhatsApp, sin cuenta: el
 * enlace va firmado y vence, así nadie puede adivinar el de otra.
 */
class PublicQuotePdfController extends Controller
{
    public function __invoke(Quote $quote, QuotePdfService $pdf): Response
    {
        // Una cotización anulada ya no se ofrece al cliente.
        abort_if($quote->estado === 'anulada', 410, 'Esta cotización fue anulada. Pide una nueva a tu asesor.');

        return $pdf->generate($quote)->stream($pdf->nombreArchivo($quote));
    }
}
