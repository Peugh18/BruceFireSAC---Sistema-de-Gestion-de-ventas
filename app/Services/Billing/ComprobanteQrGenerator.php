<?php

namespace App\Services\Billing;

use App\Models\CompanySetting;
use App\Models\ElectronicDocument;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;

class ComprobanteQrGenerator
{
    /**
     * Genera el PNG (como string binario) del QR tributario oficial de
     * SUNAT: RUC_EMISOR|TIPO_DOC|SERIE|CORRELATIVO|TOTAL_IGV|TOTAL_VENTA|
     * FECHA_EMISION|TIPO_DOC_CLIENTE|NUM_DOC_CLIENTE|HASH_XML|
     * (ver docs/FACTURACION_GREENTER_SUNAT.md §10).
     */
    public function generate(ElectronicDocument $document, string $xmlSigned): string
    {
        $document->loadMissing('sale.client');
        $sale = $document->sale;
        $company = CompanySetting::current();

        $tipoDocSunat = $document->tipo === 'factura' ? '01' : '03';
        $tipoDocCliente = match ($sale->client->tipo_documento) {
            'ruc' => '6',
            'dni' => '1',
            default => '0',
        };

        $snapshot = str_contains($xmlSigned, 'AccountingCustomerParty') ? app(DatosEmision::class)->desdeXml($xmlSigned) : null;

        $texto = implode('|', [
            $snapshot['emisor']['ruc'] ?? $company->ruc,
            $snapshot['tipo_documento'] ?? $tipoDocSunat,
            $document->serie,
            str_pad((string) $document->correlativo, 8, '0', STR_PAD_LEFT),
            number_format((float) ($snapshot['totales']['igv'] ?? $sale->igv), 2, '.', ''),
            number_format((float) ($snapshot['totales']['total'] ?? $sale->total), 2, '.', ''),
            ($document->fecha_emision ?? $sale->fecha)->toDateString(),
            $snapshot['cliente']['tipo_documento'] ?? $tipoDocCliente,
            $snapshot['cliente']['numero_documento'] ?? $sale->client->numero_documento,
            $this->extractDigestValue($xmlSigned),
        ]).'|';

        $qrCode = new QrCode($texto);
        $writer = new PngWriter;

        return $writer->write($qrCode)->getString();
    }

    /**
     * El hash que SUNAT exige en el QR es el DigestValue de la firma XML-DSig
     * ya embebido en el propio XML firmado — no lo devuelve el CDR en esta
     * versión de Greenter, así que se extrae directo del XML firmado.
     */
    protected function extractDigestValue(string $xmlSigned): string
    {
        if (preg_match('/<(?:ds:)?DigestValue>([^<]+)<\/(?:ds:)?DigestValue>/', $xmlSigned, $matches) === 1) {
            return trim($matches[1]);
        }

        return '';
    }
}
