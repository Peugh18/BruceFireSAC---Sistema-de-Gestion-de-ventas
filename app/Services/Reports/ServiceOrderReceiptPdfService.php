<?php

namespace App\Services\Reports;

use App\Models\ClientSite;
use App\Models\CompanySetting;
use App\Models\ServiceOrder;
use App\Services\Tecnico\EvidenciasParaPdf;
use Barryvdh\DomPDF\Facade\Pdf as PdfFacade;
use Barryvdh\DomPDF\PDF;

class ServiceOrderReceiptPdfService
{
    public function __construct(protected EvidenciasParaPdf $evidencias) {}

    public function generate(ServiceOrder $serviceOrder): PDF
    {
        $serviceOrder->loadMissing('client', 'sede', 'equipments', 'quote');
        $local = ClientSite::deReferencia($serviceOrder->client, $serviceOrder->referencia);

        return PdfFacade::loadView('pdf.service-order-receipt', [
            'order' => $serviceOrder,
            ...CompanySetting::current()->identidadPdf(),
            'firmaRecojo' => $this->evidencias->firma($serviceOrder, ['recojo']),
            'conteo' => $serviceOrder->conteoDeCampo(),
            'local' => $local,
        ])->setPaper('a4');
    }
}
