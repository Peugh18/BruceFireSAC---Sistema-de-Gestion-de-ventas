<?php

namespace App\Services\Reports;

use App\Models\ServiceOrder;
use Barryvdh\DomPDF\Facade\Pdf as PdfFacade;
use Barryvdh\DomPDF\PDF;

class ServiceOrderReceiptPdfService
{
    public function generate(ServiceOrder $serviceOrder): PDF
    {
        $serviceOrder->loadMissing('client', 'sede', 'equipments');

        return PdfFacade::loadView('pdf.service-order-receipt', ['order' => $serviceOrder])->setPaper('a4');
    }
}
