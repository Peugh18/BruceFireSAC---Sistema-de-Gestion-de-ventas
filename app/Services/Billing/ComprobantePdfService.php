<?php

namespace App\Services\Billing;

use App\Models\CompanyBankAccount;
use App\Models\CompanySetting;
use App\Models\ElectronicDocument;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class ComprobantePdfService
{
    public function __construct(
        protected NumeroEnLetrasService $numeroEnLetras,
        protected ComprobanteQrGenerator $qrGenerator,
        protected DetraccionCalculator $detraccionCalculator,
    ) {}

    /**
     * Genera la representación impresa (PDF) del comprobante y la guarda en
     * el disco local. Devuelve la ruta relativa (para `pdf_path`).
     */
    public function generate(ElectronicDocument $document, string $xmlSigned): string
    {
        $document->loadMissing('sale.client', 'sale.vehicle', 'sale.items.product', 'sale.items.service', 'sale.installments');
        $sale = $document->sale;
        $company = CompanySetting::current();

        $detraccion = $this->detraccionCalculator->paraVenta($sale, $document->tipo);

        $pdf = Pdf::loadView('pdf.comprobante', [
            'document' => $document,
            'sale' => $sale,
            'company' => $company,
            'logoBase64' => $this->logoBase64($company),
            'qrBase64' => base64_encode($this->qrGenerator->generate($document, $xmlSigned)),
            'montoEnLetras' => $this->numeroEnLetras->convertir((float) $sale->total),
            'bankAccounts' => CompanyBankAccount::query()->where('activo', true)->orderBy('orden')->get(),
            'detraccion' => $detraccion,
        ])->setPaper('a4');

        $documentName = "{$document->tipo}-{$document->serie}-{$document->correlativo}";
        $path = "pdf/{$documentName}.pdf";

        Storage::disk('local')->put($path, $pdf->output());

        return $path;
    }

    protected function logoBase64(CompanySetting $company): ?string
    {
        if (! $company->logo_path || ! Storage::disk('public')->exists($company->logo_path)) {
            return null;
        }

        $mime = Storage::disk('public')->mimeType($company->logo_path) ?: 'image/png';
        $contents = Storage::disk('public')->get($company->logo_path);

        return "data:{$mime};base64,".base64_encode($contents);
    }
}
