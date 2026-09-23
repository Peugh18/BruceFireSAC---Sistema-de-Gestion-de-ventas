<?php

namespace App\Actions\Billing;

use App\Contracts\SunatClientInterface;
use App\Models\ElectronicDocument;
use App\Models\Sale;
use App\Services\Billing\ComprobantePdfService;
use App\Services\Billing\GreenterService;
use App\Services\Billing\ResponseClassifier;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

class EmitElectronicDocument
{
    public function __construct(
        protected ReserveNextCorrelativo $reserveNextCorrelativo,
        protected GreenterService $greenterService,
        protected ResponseClassifier $responseClassifier,
        protected ComprobantePdfService $pdfService,
    ) {}

    public function handle(Sale $sale): ElectronicDocument
    {
        if ($sale->esNotaVenta()) {
            throw new InvalidArgumentException('Una nota de venta no se envía a SUNAT.');
        }

        $tipo = $sale->comprobante_tipo === 'factura' ? 'factura' : 'boleta';
        $serie = $this->serieFor($tipo);
        $correlativo = $this->reserveNextCorrelativo->handle($tipo, $serie);

        $document = ElectronicDocument::create([
            'sale_id' => $sale->id,
            'tipo' => $tipo,
            'serie' => $serie,
            'correlativo' => $correlativo,
            'sunat_estado' => 'pendiente',
        ]);

        return $this->sendDocument($document);
    }

    public function sendDocument(ElectronicDocument $document): ElectronicDocument
    {
        $document->loadMissing('sale.client', 'sale.items.product', 'sale.items.service', 'sale.installments');

        $invoice = $this->greenterService->buildInvoice($document->sale, $document);
        $xmlSigned = $this->greenterService->sign($invoice);
        $documentName = $invoice->getName();

        Storage::disk('local')->put("xml/{$documentName}.xml", $xmlSigned);

        $response = app(SunatClientInterface::class)->send($xmlSigned, $documentName);
        $notas = $response['notas'] ?? [];
        $cdrPath = null;

        if ($response['cdr_zip'] !== null) {
            $cdrPath = "cdr/R-{$documentName}.zip";
            Storage::disk('local')->put($cdrPath, $response['cdr_zip']);
        }

        $pdfPath = $this->pdfService->generate($document, $xmlSigned);

        $document->update([
            'xml_path' => "xml/{$documentName}.xml",
            'cdr_path' => $cdrPath ?? $document->cdr_path,
            'pdf_path' => $pdfPath,
            'sunat_estado' => $this->responseClassifier->classify((int) $response['codigo'], $notas),
            'sunat_codigo_respuesta' => (string) $response['codigo'],
            'sunat_mensaje' => $notas === [] ? $response['mensaje'] : $response['mensaje'].' | '.implode(' | ', $notas),
            'enviado_at' => now(),
        ]);

        return $document->refresh();
    }

    protected function serieFor(string $tipo): string
    {
        return config("billing.series.{$tipo}", $tipo === 'factura' ? 'F001' : 'B001');
    }
}
