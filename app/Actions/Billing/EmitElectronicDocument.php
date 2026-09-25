<?php

namespace App\Actions\Billing;

use App\Contracts\SunatClientInterface;
use App\Models\ElectronicDocument;
use App\Models\Sale;
use App\Services\Billing\ComprobantePdfService;
use App\Services\Billing\GreenterService;
use App\Services\Billing\ResponseClassifier;
use Carbon\CarbonInterface;
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

    /**
     * Emite y envía a SUNAT en el momento (sin ventana de revisión).
     */
    public function handle(Sale $sale): ElectronicDocument
    {
        return $this->sendDocument($this->crearDocumento($sale, 'pendiente'));
    }

    /**
     * Reserva el número y deja el comprobante "por enviar" con su XML firmado
     * y su PDF listos. Se envía solo cuando vence la ventana de revisión
     * (billing.envio_diferido_horas) o con "Enviar ya"; mientras tanto SUNAT
     * no lo conoce y se puede corregir sin nota de crédito.
     */
    public function programar(Sale $sale, ?CarbonInterface $fechaEmision = null): ElectronicDocument
    {
        $horas = (float) config('billing.envio_diferido_horas');

        if ($horas <= 0) {
            return $this->handle($sale);
        }

        $document = $this->crearDocumento($sale, 'por_enviar', $fechaEmision);
        $document->update(['enviar_desde' => now()->addMinutes((int) round($horas * 60))]);

        $this->prepararDocumento($document);

        return $document->refresh();
    }

    public function sendDocument(ElectronicDocument $document): ElectronicDocument
    {
        ['xml' => $xmlSigned, 'nombre' => $documentName] = $this->prepararDocumento($document);

        $response = app(SunatClientInterface::class)->send($xmlSigned, $documentName);
        $notas = $response['notas'] ?? [];
        $cdrPath = null;

        if ($response['cdr_zip'] !== null) {
            $cdrPath = "cdr/R-{$documentName}.zip";
            Storage::disk('local')->put($cdrPath, $response['cdr_zip']);
        }

        $document->update([
            'cdr_path' => $cdrPath ?? $document->cdr_path,
            'sunat_estado' => $this->responseClassifier->classify((int) $response['codigo'], $notas),
            'sunat_codigo_respuesta' => (string) $response['codigo'],
            'sunat_mensaje' => $notas === [] ? $response['mensaje'] : $response['mensaje'].' | '.implode(' | ', $notas),
            'enviado_at' => now(),
        ]);

        return $document->refresh();
    }

    /**
     * Arma y firma el XML con los datos actuales de la venta y regenera el
     * PDF. Se llama al programar, al corregir y justo antes de enviar.
     *
     * @return array{xml: string, nombre: string}
     */
    public function prepararDocumento(ElectronicDocument $document): array
    {
        $document->unsetRelation('sale');
        $document->loadMissing('sale.client', 'sale.items.product', 'sale.items.service', 'sale.installments', 'cpeAfectado');

        $esNota = in_array($document->tipo, ['nota_credito', 'nota_debito'], true);

        $invoice = $esNota
            ? $this->greenterService->buildNote($document)
            : $this->greenterService->buildInvoice($document->sale, $document);
        $xmlSigned = $this->greenterService->sign($invoice);
        $documentName = $invoice->getName();

        Storage::disk('local')->put("xml/{$documentName}.xml", $xmlSigned);

        $pdfPath = $esNota ? null : $this->pdfService->generate($document, $xmlSigned);

        $document->update([
            'xml_path' => "xml/{$documentName}.xml",
            'pdf_path' => $pdfPath ?? $document->pdf_path,
        ]);

        return ['xml' => $xmlSigned, 'nombre' => $documentName];
    }

    /**
     * Descarta un comprobante que nunca llegó a SUNAT: borra su XML y PDF y
     * devuelve el número a la serie para que lo use el siguiente.
     */
    public function descartarPorEnviar(ElectronicDocument $document): void
    {
        if (! $document->estaPorEnviar()) {
            throw new InvalidArgumentException('Solo se descarta un comprobante que aún no se envió a SUNAT.');
        }

        Storage::disk('local')->delete(array_filter([$document->xml_path, $document->pdf_path]));

        $this->reserveNextCorrelativo->liberar($document->tipo, $document->serie, $document->correlativo);

        $document->delete();
    }

    protected function crearDocumento(Sale $sale, string $estado, ?CarbonInterface $fechaEmision = null): ElectronicDocument
    {
        if ($sale->esNotaVenta()) {
            throw new InvalidArgumentException('Una nota de venta no se envía a SUNAT.');
        }

        $tipo = $sale->comprobante_tipo === 'factura' ? 'factura' : 'boleta';
        $serie = $this->serieFor($tipo);
        $correlativo = $this->reserveNextCorrelativo->handle($tipo, $serie);

        return ElectronicDocument::create([
            'sale_id' => $sale->id,
            'tipo' => $tipo,
            'serie' => $serie,
            'correlativo' => $correlativo,
            'fecha_emision' => ($fechaEmision ?? $sale->fecha)->toDateString(),
            'sunat_estado' => $estado,
        ]);
    }

    protected function serieFor(string $tipo): string
    {
        return config("billing.series.{$tipo}", $tipo === 'factura' ? 'F001' : 'B001');
    }
}
