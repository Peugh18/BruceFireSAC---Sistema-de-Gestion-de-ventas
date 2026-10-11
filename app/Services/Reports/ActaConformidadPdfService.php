<?php

namespace App\Services\Reports;

use App\Models\CompanySetting;
use App\Models\ServiceOrder;
use App\Services\Tecnico\EvidenciasParaPdf;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DompdfWrapper;

class ActaConformidadPdfService
{
    public function __construct(protected EvidenciasParaPdf $evidencias) {}

    /**
     * Genera el PDF del Acta de Conformidad de Servicio y Entrega de Equipos (§23).
     */
    public function generate(ServiceOrder $serviceOrder): DompdfWrapper
    {
        $serviceOrder->loadMissing([
            'client',
            'sede',
            'vehicle',
            'equipments',
            'events' => fn ($q) => $q->latest('created_at'),
        ]);

        $company = CompanySetting::current();

        $custodyEvent = $serviceOrder->events
            ->first(fn ($e) => isset($e->payload['eslabon_custodia']) && $e->payload['eslabon_custodia'] === 'entrega_campo')
            ?? $serviceOrder->events->first(fn ($e) => isset($e->payload['eslabon_custodia']));

        return Pdf::loadView('pdf.acta-conformidad', [
            'order' => $serviceOrder,
            'client' => $serviceOrder->client,
            'sede' => $serviceOrder->sede,
            'equipments' => $serviceOrder->equipments,
            'company' => $company,
            'logo' => $company->logoParaPdf(),
            'custody' => $custodyEvent?->payload ?? [],
            'firmaCliente' => $this->evidencias->firma($serviceOrder, ['entrega', 'instalacion', 'inspeccion', 'mantenimiento']),
            'fotos' => $this->evidencias->fotos($serviceOrder, ['antes', 'despues']),
            'fechaEntrega' => $custodyEvent?->created_at?->format('d/m/Y H:i') ?? now()->format('d/m/Y H:i'),
        ])->setPaper('a4', 'portrait');
    }
}
