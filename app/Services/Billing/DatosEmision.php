<?php

namespace App\Services\Billing;

use App\Models\ElectronicDocument;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class DatosEmision
{
    /** @return array<string, mixed>|null */
    public function recuperar(ElectronicDocument $document): ?array
    {
        if ($document->datos_emision !== null) {
            return $document->datos_emision;
        }

        if (! $document->xml_path || ! Storage::disk('local')->exists($document->xml_path)) {
            return null;
        }

        $datos = $this->desdeXml((string) Storage::disk('local')->get($document->xml_path));
        $document->forceFill(['datos_emision' => $datos])->saveQuietly();

        return $datos;
    }

    /** @return array<string, mixed> */
    public function desdeXml(string $xml): array
    {
        $dom = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        try {
            $valid = $dom->loadXML($xml, LIBXML_NONET);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        if (! $valid || ! $dom->documentElement) {
            throw new RuntimeException('No se puede recuperar la copia del comprobante: XML inválido.');
        }
        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('cac', 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');
        $xpath->registerNamespace('cbc', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');
        $root = $dom->documentElement;
        $get = fn (string $path, ?DOMElement $node = null): string => trim((string) $xpath->evaluate("string({$path})", $node ?? $root));
        $party = 'cac:AccountingCustomerParty/cac:Party';
        $totales = ['gravadas' => 0.0, 'exoneradas' => 0.0, 'inafectas' => 0.0, 'igv' => (float) $get('cac:TaxTotal/cbc:TaxAmount'), 'total' => (float) $get('cac:LegalMonetaryTotal/cbc:PayableAmount | cac:RequestedMonetaryTotal/cbc:PayableAmount')];
        foreach (['1000' => 'gravadas', '9997' => 'exoneradas', '9998' => 'inafectas'] as $id => $key) {
            $totales[$key] = (float) $get("cac:TaxTotal/cac:TaxSubtotal[cac:TaxCategory/cac:TaxScheme/cbc:ID='{$id}']/cbc:TaxableAmount");
        }
        $lineas = [];
        foreach ($xpath->query('cac:InvoiceLine | cac:CreditNoteLine | cac:DebitNoteLine', $root) ?: [] as $line) {
            if (! $line instanceof DOMElement) {
                continue;
            }
            $cantidad = (float) $get('cbc:InvoicedQuantity | cbc:CreditedQuantity | cbc:DebitedQuantity', $line);
            $base = (float) $get('cbc:LineExtensionAmount', $line);
            $igv = (float) $get('cac:TaxTotal/cbc:TaxAmount', $line);
            $lineas[] = [
                'codigo' => $get('cac:Item/cac:SellersItemIdentification/cbc:ID', $line),
                'nombre' => $get('cac:Item/cbc:Description', $line),
                'unidad_medida' => $get('cbc:InvoicedQuantity/@unitCode | cbc:CreditedQuantity/@unitCode | cbc:DebitedQuantity/@unitCode', $line),
                'cantidad' => $cantidad,
                'precio_unitario' => (float) $get('cac:PricingReference/cac:AlternativeConditionPrice/cbc:PriceAmount', $line),
                'valor_unitario' => (float) $get('cac:Price/cbc:PriceAmount', $line),
                'base' => $base, 'igv' => $igv, 'subtotal' => round($base + $igv, 2),
                'descuento_base' => (float) $get('cac:AllowanceCharge/cbc:Amount', $line),
                'descuento' => $get('cac:AllowanceCharge/cbc:Amount', $line) !== '' ? round($cantidad * (float) $get('cac:PricingReference/cac:AlternativeConditionPrice/cbc:PriceAmount', $line) - $base - $igv, 2) : 0.0,
                'tipo_afectacion_igv' => $get('cac:TaxTotal/cac:TaxSubtotal/cac:TaxCategory/cbc:TaxExemptionReasonCode', $line),
            ];
        }
        if ($lineas === []) {
            throw new RuntimeException('El XML guardado no contiene líneas recuperables del comprobante.');
        }

        $cuotas = [];
        foreach ($xpath->query("cac:PaymentTerms[cbc:ID='FormaPago' and starts-with(cbc:PaymentMeansID, 'Cuota')]", $root) ?: [] as $cuota) {
            if ($cuota instanceof DOMElement) {
                $cuotas[] = ['monto' => (float) $get('cbc:Amount', $cuota), 'fecha' => $get('cbc:PaymentDueDate', $cuota)];
            }
        }

        return [
            'cliente' => [
                'tipo_documento' => $get("{$party}/cac:PartyIdentification/cbc:ID/@schemeID"),
                'numero_documento' => $get("{$party}/cac:PartyIdentification/cbc:ID"),
                'razon_social' => $get("{$party}/cac:PartyLegalEntity/cbc:RegistrationName"),
                'direccion_fiscal' => $get("{$party}/cac:PartyLegalEntity/cac:RegistrationAddress/cac:AddressLine/cbc:Line"),
            ],
            'emisor' => [
                'ruc' => $get('cac:AccountingSupplierParty/cac:Party/cac:PartyIdentification/cbc:ID'),
                'razon_social' => $get('cac:AccountingSupplierParty/cac:Party/cac:PartyLegalEntity/cbc:RegistrationName'),
                'direccion' => $get('cac:AccountingSupplierParty/cac:Party/cac:PartyLegalEntity/cac:RegistrationAddress/cac:AddressLine/cbc:Line'),
                'nombre_comercial' => $get('cac:AccountingSupplierParty/cac:Party/cac:PartyName/cbc:Name'),
                'ubigeo' => $get('cac:AccountingSupplierParty/cac:Party/cac:PartyLegalEntity/cac:RegistrationAddress/cbc:ID'),
                'departamento' => $get('cac:AccountingSupplierParty/cac:Party/cac:PartyLegalEntity/cac:RegistrationAddress/cbc:CountrySubentity'),
                'provincia' => $get('cac:AccountingSupplierParty/cac:Party/cac:PartyLegalEntity/cac:RegistrationAddress/cbc:CityName'),
                'distrito' => $get('cac:AccountingSupplierParty/cac:Party/cac:PartyLegalEntity/cac:RegistrationAddress/cbc:District'),
            ],
            'tipo_documento' => $get('cbc:InvoiceTypeCode') ?: ($root->localName === 'CreditNote' ? '07' : '08'),
            'numero' => $get('cbc:ID'), 'fecha' => $get('cbc:IssueDate'), 'hora' => $get('cbc:IssueTime'),
            'moneda' => $get('cbc:DocumentCurrencyCode'),
            'referencia' => ['tipo' => $get('cac:BillingReference/cac:InvoiceDocumentReference/cbc:DocumentTypeCode'), 'numero' => $get('cac:BillingReference/cac:InvoiceDocumentReference/cbc:ID')],
            'lineas' => $lineas, 'totales' => $totales,
            'observaciones' => $get('cbc:Note[not(@languageLocaleID)]'),
            'pago' => [
                'tipo' => $get("cac:PaymentTerms[cbc:ID='FormaPago' and not(starts-with(cbc:PaymentMeansID, 'Cuota'))]/cbc:PaymentMeansID"),
                'neto_pendiente' => (float) $get("cac:PaymentTerms[cbc:ID='FormaPago' and not(starts-with(cbc:PaymentMeansID, 'Cuota'))]/cbc:Amount"),
                'cuotas' => $cuotas,
            ],
            'detraccion' => [
                'aplica' => $get("cac:PaymentTerms[cbc:ID='Detraccion']/cbc:PaymentMeansID") !== '',
                'codigo_bien' => $get("cac:PaymentTerms[cbc:ID='Detraccion']/cbc:PaymentMeansID"),
                'monto' => (float) $get("cac:PaymentTerms[cbc:ID='Detraccion']/cbc:Amount"),
                'cuenta' => $get("cac:PaymentMeans[cbc:ID='Detraccion']/cac:PayeeFinancialAccount/cbc:ID"),
            ],
        ];
    }
}
