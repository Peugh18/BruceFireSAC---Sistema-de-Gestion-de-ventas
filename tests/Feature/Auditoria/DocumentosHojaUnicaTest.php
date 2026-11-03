<?php

use App\Models\CompanySetting;
use App\Models\Quote;
use App\Models\ServiceOrder;
use App\Services\Quotes\QuotePdfService;
use App\Services\Reports\ActaConformidadPdfService;
use App\Services\Reports\ServiceOrderReceiptPdfService;

/*
| Cotización, constancia y acta usan la misma hoja que los comprobantes
| (resources/views/pdf/hoja): logo, datos y color que elige el Gerente.
*/

it('dibuja cada documento con el color de la marca del Gerente', function (Closure $pdf) {
    CompanySetting::current()->update(['color_marca' => '#1A5FB4']);

    $html = $pdf()->getDomPDF()->outputHtml();

    expect($html)->toContain('#1A5FB4');
})->with([
    'cotización' => fn () => app(QuotePdfService::class)->generate(Quote::factory()->create()),
    'constancia' => fn () => app(ServiceOrderReceiptPdfService::class)->generate(ServiceOrder::factory()->create()),
    'acta' => fn () => app(ActaConformidadPdfService::class)->generate(ServiceOrder::factory()->create()),
]);
