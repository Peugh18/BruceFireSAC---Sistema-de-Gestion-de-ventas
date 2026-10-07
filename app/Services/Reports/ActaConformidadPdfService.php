<?php

namespace App\Services\Reports;

use App\Models\CompanySetting;
use App\Models\ServiceOrder;
use App\Services\Tecnico\EvidenciasParaPdf;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DompdfWrapper;
use Illuminate\Support\Facades\Storage;

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
            'logoBase64' => $this->logoBase64($company),
            'custody' => $custodyEvent?->payload ?? [],
            'firmaCliente' => $this->evidencias->firma($serviceOrder, ['entrega', 'instalacion', 'inspeccion', 'mantenimiento']),
            'fotos' => $this->evidencias->fotos($serviceOrder, ['antes', 'despues']),
            'fechaEntrega' => $custodyEvent?->created_at?->format('d/m/Y H:i') ?? now()->format('d/m/Y H:i'),
        ])->setPaper('a4', 'portrait');
    }

    protected function logoBase64(CompanySetting $company): ?string
    {
        if (! $company->logo_path || ! Storage::disk('public')->exists($company->logo_path)) {
            return null;
        }

        $mime = Storage::disk('public')->mimeType($company->logo_path) ?: 'image/png';
        $contents = Storage::disk('public')->get($company->logo_path);

        return 'data:'.$mime.';base64,'.base64_encode($contents);
    }
}
