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
        return $pdf->generate($quote)->stream($pdf->nombreArchivo($quote));
    }
}
