<?php

namespace App\Services\Billing;

use App\Contracts\SunatClientInterface;
use Greenter\Model\Response\BaseResult;
use Greenter\Model\Response\BillResult;
use Greenter\Model\Response\SummaryResult;
use Greenter\Ws\Services\BillSender;
use Greenter\Ws\Services\ConsultCdrService;
use Greenter\Ws\Services\ExtService;
use Greenter\Ws\Services\SoapClient;
use Greenter\Ws\Services\SummarySender;
use Greenter\Ws\Services\SunatEndpoints;
use Greenter\Ws\Services\WsdlProvider;

/**
 * Envía a SUNAT sin pasar por Greenter\See: su SoapClient de fábrica se
 * conecta con verify_peer=false y See no deja cambiarlo. Aquí se arma el
 * mismo SoapClient de Greenter pero verificando el certificado TLS del
 * servidor, y se usan sus mismos BillSender/SummarySender/ExtService (que
 * comprimen el XML con ZipFly y leen el CDR igual que See).
 */
class GreenterSunatClient implements SunatClientInterface
{
    /**
     * Endpoint SOAP segun la configuracion beta/produccion.
     */
    public function resolveEndpoint(): string
    {
        return config('billing.sunat.beta')
            ? SunatEndpoints::FE_BETA
            : SunatEndpoints::FE_PRODUCCION;
    }

    /**
     * Sin "cafile" PHP usa los certificados raíz del sistema (en Windows, el
     * almacén de Windows; en Linux, el paquete ca-certificates). Verificado
     * en el PHP de Laragon contra e-beta y e-factura el 2026-10-06.
     */
    public function soapClient(): SoapClient
    {
        $client = new SoapClient(WsdlProvider::getBillPath(), [
            'stream_context' => stream_context_create([
                'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
            ]),
            'cache_wsdl' => WSDL_CACHE_MEMORY,
        ]);
        $client->setService($this->resolveEndpoint());
        $client->setCredentials(
            config('billing.sunat.ruc').config('billing.sunat.usuario_sol'),
            (string) config('billing.sunat.clave_sol'),
        );

        return $client;
    }

    /**
     * @return array{cdr_zip:string|null,codigo:int,mensaje:string,notas:list<string>}
     */
    public function send(string $xmlSigned, string $documentName): array
    {
        $sender = new BillSender;
        $sender->setClient($this->soapClient());
        $result = $sender->send($documentName, $xmlSigned);

        if (! $result instanceof BillResult || ! $result->isSuccess()) {
            return $this->error($result);
        }

        return $this->conCdr($result);
    }

    /**
     * @return array{ticket:string|null,mensaje:string}
     */
    public function sendSummary(string $xmlSigned, string $documentName): array
    {
        $sender = new SummarySender;
        $sender->setClient($this->soapClient());
        $result = $sender->send($documentName, $xmlSigned);

        if (! $result instanceof SummaryResult || ! $result->isSuccess() || ! $result->getTicket()) {
            return ['ticket' => null, 'mensaje' => $result?->getError()?->getMessage() ?: 'Error de comunicacion con SUNAT.'];
        }

        return ['ticket' => (string) $result->getTicket(), 'mensaje' => 'Recibido por SUNAT, en proceso.'];
    }

    /**
     * @return array{en_proceso:bool,cdr_zip:string|null,codigo:int,mensaje:string,notas:list<string>}
     */
    public function getStatus(string $ticket): array
    {
        $service = new ExtService;
        $service->setClient($this->soapClient());
        $result = $service->getStatus($ticket);

        if ($result->getCode() === '98') {
            return ['en_proceso' => true, 'cdr_zip' => null, 'codigo' => 98, 'mensaje' => 'SUNAT aún está procesando el ticket.', 'notas' => []];
        }

        return ['en_proceso' => false, ...($result->getCdrResponse() ? $this->conCdr($result) : $this->error($result))];
    }

    /**
     * Estado real de un comprobante ya enviado, por si la respuesta de SUNAT
     * se perdio. Se usa ConsultCdrService (getStatusCdr) con el mismo cliente
     * TLS que el envio.
     *
     * @return array{estado:'registrado'|'no_registrado'|'sin_respuesta',cdr_zip:string|null,codigo:int,mensaje:string,notas:list<string>}
     */
    public function consultCdr(string $ruc, string $tipoDoc, string $serie, int $numero): array
    {
        $service = new ConsultCdrService;
        $service->setClient($this->soapClient());

        try {
            $result = $service->getStatusCdr($ruc, $tipoDoc, $serie, $numero);
        } catch (\Throwable $e) {
            return $this->sinRespuesta('No hubo respuesta de SUNAT al consultar el comprobante.');
        }

        $cdr = $result->getCdrResponse();

        if ($cdr !== null && $cdr->getCode() !== null) {
            return [
                'estado' => 'registrado',
                'cdr_zip' => $result->getCdrZip(),
                'codigo' => (int) $cdr->getCode(),
                'mensaje' => $cdr->getDescription() ?? 'Sin descripcion SUNAT',
                'notas' => array_values($cdr->getNotes() ?? []),
            ];
        }

        // Un SoapFault no trae codigo: no prueba que el comprobante no exista,
        // asi que no se puede dar por no registrado.
        if ($result->isSuccess() || $result->getCode() !== null) {
            return [
                'estado' => 'no_registrado',
                'cdr_zip' => null,
                'codigo' => 0,
                'mensaje' => 'SUNAT no tiene registrado este comprobante.',
                'notas' => [],
            ];
        }

        return $this->sinRespuesta($result->getError()?->getMessage() ?: 'No hubo respuesta de SUNAT al consultar el comprobante.');
    }

    /**
     * @return array{estado:'sin_respuesta',cdr_zip:null,codigo:0,mensaje:string,notas:list<string>}
     */
    protected function sinRespuesta(string $mensaje): array
    {
        return ['estado' => 'sin_respuesta', 'cdr_zip' => null, 'codigo' => 0, 'mensaje' => $mensaje, 'notas' => []];
    }

    /**
     * Solo se acepta lo que trae un CDR legible (S13).
     *
     * @return array{cdr_zip:string|null,codigo:int,mensaje:string,notas:list<string>}
     */
    protected function conCdr(BillResult $result): array
    {
        $cdr = $result->getCdrResponse();

        if ($cdr === null || $cdr->getCode() === null) {
            return ['cdr_zip' => null, 'codigo' => 500, 'mensaje' => 'SUNAT no devolvio una respuesta CDR valida.', 'notas' => []];
        }

        return [
            'cdr_zip' => $result->getCdrZip(),
            'codigo' => (int) $cdr->getCode(),
            'mensaje' => $cdr->getDescription() ?? 'Sin descripcion SUNAT',
            'notas' => array_values($cdr->getNotes() ?? []),
        ];
    }

    /**
     * @return array{cdr_zip:null,codigo:int,mensaje:string,notas:list<string>}
     */
    protected function error(?BaseResult $result): array
    {
        $error = $result?->getError();

        return [
            'cdr_zip' => null,
            // Igual que antes: todo error sin CDR se clasifica como excepción.
            'codigo' => 500,
            'mensaje' => $error?->getMessage() ?: 'Error de comunicacion con SUNAT.',
            'notas' => [],
        ];
    }
}
