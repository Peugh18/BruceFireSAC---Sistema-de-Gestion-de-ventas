<?php

namespace App\Services\Billing;

use App\Models\CompanySetting;
use App\Models\DispatchGuide;
use App\Models\Ubigeo;
use App\Support\HojaPdf;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

/**
 * Representación impresa de la guía de remisión remitente con la hoja única
 * (misma cabecera, datos, tabla y pie que la factura). El QR es el enlace de
 * consulta que SUNAT devuelve en el CDR de la GRE; sin CDR no lleva QR.
 */
class GuiaRemisionPdfService
{
    public function generate(DispatchGuide $guide): string
    {
        $guide->loadMissing('items', 'vehiculo', 'conductor', 'client');
        $enlace = $this->enlaceDeConsulta($guide);

        return HojaPdf::cargar('pdf.guia-remision', [
            ...CompanySetting::current()->identidadPdf(),
            'guia' => $guide,
            'partida' => $this->lugar($guide->partida_ubigeo),
            'llegada' => $this->lugar($guide->llegada_ubigeo),
            'qrBase64' => $enlace ? base64_encode((new PngWriter)->write(new QrCode($enlace))->getString()) : null,
            'enlaceConsulta' => $enlace,
        ])->output();
    }

    /**
     * El CDR de la GRE trae en cbc:DocumentDescription el enlace con el que
     * cualquiera consulta la guía en SUNAT.
     */
    protected function enlaceDeConsulta(DispatchGuide $guide): ?string
    {
        if (! $guide->cdr_path || ! Storage::disk('local')->exists($guide->cdr_path)) {
            return null;
        }
        $zip = new ZipArchive;
        if ($zip->open(Storage::disk('local')->path($guide->cdr_path)) !== true) {
            return null;
        }
        $xml = '';
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $xml .= (string) $zip->getFromIndex($i);
        }
        $zip->close();

        return preg_match('#<cbc:DocumentDescription>\s*(https?://[^<\s]+)\s*</cbc:DocumentDescription>#', $xml, $m) === 1
            ? html_entity_decode($m[1])
            : null;
    }

    protected function lugar(?string $ubigeo): string
    {
        return $ubigeo ? (Ubigeo::query()->find($ubigeo)?->etiqueta() ?? $ubigeo) : '';
    }
}
