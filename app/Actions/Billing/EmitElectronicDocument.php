<?php

namespace App\Actions\Billing;

use App\Contracts\SunatClientInterface;
use App\Models\ElectronicDocument;
use App\Models\Sale;
use App\Services\Billing\ComprobantePdfService;
use App\Services\Billing\DatosEmision;
use App\Services\Billing\GreenterService;
use App\Services\Billing\ResponseClassifier;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
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
        // Un doble clic en «Enviar ya» puede coincidir con el programador: el
        // bloqueo por documento garantiza un solo envío a la vez y que solo
        // una respuesta escriba el estado.
        $lock = Cache::lock("sunat-envio-{$document->id}", 300);

        if (! $lock->get()) {
            throw ValidationException::withMessages([
                'estado' => 'Este comprobante ya se está enviando a SUNAT. Espera su respuesta antes de volver a intentarlo.',
            ]);
        }

        try {
            ['xml' => $xmlSigned, 'nombre' => $documentName] = $this->prepararDocumento($document);

            $document->forceFill(['intento_envio_at' => $document->intento_envio_at ?? now()])->saveQuietly();

            return $this->registrarRespuesta($document, app(SunatClientInterface::class)->send($xmlSigned, $documentName), $documentName);
        } finally {
            $lock->release();
        }
    }

    /**
     * Deja registrado en el comprobante lo que SUNAT respondió, con CDR o sin
     * él. Lo usan el envío y la consulta del CDR de un comprobante ya enviado.
     *
     * @param  array{cdr_zip:string|null,codigo:int,mensaje:string,notas?:list<string>}  $response
     */
    public function registrarRespuesta(ElectronicDocument $document, array $response, ?string $documentName = null): ElectronicDocument
    {
        $documentName ??= pathinfo((string) $document->xml_path, PATHINFO_FILENAME);
        $notas = $response['notas'] ?? [];
        $cdrPath = null;

        if ($response['cdr_zip'] !== null && $documentName !== '') {
            $cdrPath = "cdr/R-{$documentName}.zip";
            Storage::disk('local')->put($cdrPath, $response['cdr_zip']);
        }

        $document->update([
            'cdr_path' => $cdrPath ?? $document->cdr_path,
            'sunat_estado' => $this->responseClassifier->classify((int) $response['codigo'], $notas),
            'sunat_codigo_respuesta' => (string) $response['codigo'],
            'sunat_mensaje' => $notas === [] ? $response['mensaje'] : $response['mensaje'].' | '.implode(' | ', $notas),
            // Fecha de recepción del CDR. Si el CDR se recupera después (una
            // consulta porque la respuesta se perdió), se usa el primer intento
            // de envío: es la más conservadora para el plazo de 7 días de la
            // comunicación de baja, que no debe parecer más largo de lo legal.
            'enviado_at' => $document->enviado_at ?? $document->intento_envio_at ?? now(),
        ]);

        // Una nota de crédito de anulación aceptada anula la venta.
        app(IssueCreditNote::class)->aplicarSiFueAceptada($document->refresh());
        // V4/S11: la nota aceptada ajusta el saldo por cobrar.
        app(AplicarNotaAlSaldo::class)->handle($document->refresh());

        return $document->refresh();
    }

    /**
     * Arma y firma el XML con los datos actuales de la venta y regenera el
     * PDF mientras el comprobante está "por enviar" (aún se puede corregir).
     * Una vez que llegó a SUNAT al menos una vez, el XML queda congelado: un
     * reintento envía exactamente el mismo XML firmado.
     *
     * @return array{xml: string, nombre: string}
     */
    public function prepararDocumento(ElectronicDocument $document): array
    {
        if ((! $document->estaPorEnviar() || $document->intento_envio_at !== null) && $document->xml_path && Storage::disk('local')->exists($document->xml_path)) {
            app(DatosEmision::class)->recuperar($document);

            return [
                'xml' => (string) Storage::disk('local')->get($document->xml_path),
                'nombre' => pathinfo($document->xml_path, PATHINFO_FILENAME),
            ];
        }

        if ($document->intento_envio_at !== null || $document->enviado_at !== null || in_array($document->sunat_estado, ['aceptado', 'observado', 'rechazado', 'excepcion', 'baja_pendiente', 'anulado'], true)) {
            throw new \RuntimeException('No se puede reenviar el comprobante: falta el XML firmado original.');
        }

        $document->unsetRelation('sale');
        $document->loadMissing('sale.client', 'sale.items.product', 'sale.items.service', 'sale.installments', 'cpeAfectado');

        $esNota = in_array($document->tipo, ['nota_credito', 'nota_debito'], true);

        $invoice = $esNota
            ? $this->greenterService->buildNote($document)
            : $this->greenterService->buildInvoice($document->sale, $document);
        $xmlSigned = $this->greenterService->sign($invoice);
        $documentName = $invoice->getName();

        Storage::disk('local')->put("xml/{$documentName}.xml", $xmlSigned);

        $document->forceFill(['datos_emision' => app(DatosEmision::class)->desdeXml($xmlSigned)])->saveQuietly();

        $pdfPath = $this->pdfService->generate($document, $xmlSigned);

        $document->update([
            'xml_path' => "xml/{$documentName}.xml",
            'pdf_path' => $pdfPath,
        ]);

        return ['xml' => $xmlSigned, 'nombre' => $documentName];
    }

    /**
     * Descarta un comprobante que nunca llegó a SUNAT: borra su XML y PDF y
     * devuelve el número a la serie para que lo use el siguiente.
     *
     * Toma el MISMO bloqueo que el envío (`sunat-envio-{id}`) y revalida
     * `intento_envio_at` bajo él (M4): si el programador de envío o un «Enviar
     * ya» empezaron mientras se anulaba la venta, no se borra el XML firmado
     * ni se libera el correlativo de un comprobante que ya salió a SUNAT.
     */
    public function descartarPorEnviar(ElectronicDocument $document): void
    {
        $lock = Cache::lock("sunat-envio-{$document->id}", 300);

        if (! $lock->get()) {
            throw ValidationException::withMessages([
                'comprobante' => 'Este comprobante se está enviando a SUNAT: espera su respuesta antes de descartarlo.',
            ]);
        }

        try {
            // Releído bajo el bloqueo: el envío pudo empezar desde que se
            // cargó la pantalla.
            $document->refresh();

            if (! $document->estaPorEnviar() || $document->intento_envio_at !== null) {
                throw new InvalidArgumentException('Solo se descarta un comprobante que aún no tuvo ningún intento de envío a SUNAT.');
            }

            Storage::disk('local')->delete(array_filter([$document->xml_path, $document->pdf_path]));

            $this->reserveNextCorrelativo->liberar($document->tipo, $document->serie, $document->correlativo);

            $document->delete();
        } finally {
            $lock->release();
        }
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
            // S5: se guarda como datetime (con hora) para que el XML y el PDF
            // muestren la hora de emision real, no las 00:00:00 del DATE.
            'fecha_emision' => $fechaEmision ?? now(),
            'sunat_estado' => $estado,
        ]);
    }

    protected function serieFor(string $tipo): string
    {
        return config("billing.series.{$tipo}", $tipo === 'factura' ? 'F001' : 'B001');
    }
}
