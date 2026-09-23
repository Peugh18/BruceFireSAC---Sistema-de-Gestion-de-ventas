<?php

namespace App\Services\Certificates;

use App\Models\Certificate;
use App\Models\CompanySetting;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DompdfWrapper;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Support\Facades\Storage;

class CertificatePdfService
{
    /**
     * Genera el PDF de un certificado (operatividad/garantía o prueba
     * hidrostática) con QR hacia la página pública de verificación.
     */
    public function generate(Certificate $certificate): DompdfWrapper
    {
        $certificate->loadMissing(['certificateType', 'client', 'certificateUnits']);

        $company = CompanySetting::current();

        return Pdf::loadView('pdf.certificado', [
            'certificate' => $certificate,
            'client' => $certificate->client,
            'certificateType' => $certificate->certificateType,
            'units' => $certificate->certificateUnits,
            'company' => $company,
            'logoBase64' => $this->logoBase64($company),
            'qrBase64' => base64_encode($this->generateQr($certificate)),
        ])->setPaper('a4', 'portrait');
    }

    protected function generateQr(Certificate $certificate): string
    {
        $url = route('certificados.verificar', ['token' => $certificate->qr_token]);

        $qrCode = new QrCode($url);
        $writer = new PngWriter;

        return $writer->write($qrCode)->getString();
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
