<?php

namespace App\Services\Reports;

use App\Models\ServiceOrder;
use App\Services\Tecnico\EvidenciasParaPdf;
use Barryvdh\DomPDF\Facade\Pdf as PdfFacade;
use Barryvdh\DomPDF\PDF;

class ServiceOrderReceiptPdfService
{
    public function __construct(protected EvidenciasParaPdf $evidencias) {}

    public function generate(ServiceOrder $serviceOrder): PDF
    {
        $serviceOrder->loadMissing('client', 'sede', 'equipments');

        return PdfFacade::loadView('pdf.service-order-receipt', [
            'order' => $serviceOrder,
            'firmaRecojo' => $this->evidencias->firma($serviceOrder, ['recojo']),
        ])->setPaper('a4');
    }
}
