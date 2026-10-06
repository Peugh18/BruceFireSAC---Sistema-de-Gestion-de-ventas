<?php

namespace App\Services\Billing;

use App\Contracts\SunatClientInterface;
use Greenter\Model\Response\BillResult;
use Greenter\Model\Sale\Invoice;
use Greenter\Model\Sale\Note;
use Greenter\See;
use Greenter\Ws\Services\SunatEndpoints;
use RuntimeException;

class GreenterSunatClient implements SunatClientInterface
{
    /**
     * Devuelve el endpoint SOAP segun la configuracion beta/produccion.
     * Publico para poder comprobarlo en tests sin enviar nada a SUNAT.
     */
    public function resolveEndpoint(): string
    {
        return config('billing.sunat.beta')
            ? SunatEndpoints::FE_BETA
            : SunatEndpoints::FE_PRODUCCION;
    }

    /**
     * Verifica que el endpoint configurado utilice conexion segura TLS (HTTPS).
     */
    public function verifyTlsConfig(): bool
    {
        $endpoint = $this->resolveEndpoint();

        return str_starts_with(strtolower($endpoint), 'https://');
    }

    /**
     * @return array{cdr_zip:string|null,codigo:int,mensaje:string,notas:list<string>}
     */
    public function send(string $xmlSigned, string $documentName): array
    {
        if (! $this->verifyTlsConfig()) {
            throw new RuntimeException('El endpoint SUNAT requiere una conexion TLS segura (HTTPS).');
        }

        $certPath = config('billing.sunat.cert_path');

        if (! is_string($certPath) || ! file_exists($certPath)) {
            throw new RuntimeException('Certificado SUNAT no configurado');
        }

        $see = new See;
        $see->setCertificate(file_get_contents($certPath));
        $see->setClaveSOL(
            (string) config('billing.sunat.ruc'),
            (string) config('billing.sunat.usuario_sol'),
            (string) config('billing.sunat.clave_sol'),
        );
        // Elige el endpoint segun el ambiente: beta (pruebas) o produccion.
        // Sin esta llamada, Greenter siempre usaria su URL por defecto (beta),
        // con lo que SUNAT_BETA=false no tendria ningun efecto (auditoria S1).
        $see->setService($this->resolveEndpoint());

        // El primer parametro debe ser el FQCN del documento (lo que
        // XmlBuilderResolver/WsSenderResolver esperan para resolver builder y
        // sender), no un alias corto como 'invoice': con un alias,
        // XmlBuilderResolver::findBuilderType() revienta con
        // substr(strrchr('invoice', '\\'), 1) porque 'invoice' no tiene '\\'.
        // Solo se soportan Factura/Boleta por ahora (GreenterService::buildInvoice).
        // Nombre Greenter: RUC-TIPODOC-SERIE-CORRELATIVO. 07 y 08 son notas.
        $tipoDoc = explode('-', $documentName)[1] ?? '';
        $result = $see->sendXml(in_array($tipoDoc, ['07', '08'], true) ? Note::class : Invoice::class, $documentName, $xmlSigned);

        if (! $result || ! $result->isSuccess()) {
            $error = $result?->getError();

            return [
                'cdr_zip' => null,
                'codigo' => 500,
                'mensaje' => $error?->getMessage() ?? 'Error de comunicacion con SUNAT.',
                'notas' => [],
            ];
        }

        if (! $result instanceof BillResult) {
            return [
                'cdr_zip' => null,
                'codigo' => 500,
                'mensaje' => 'SUNAT no devolvio una respuesta CDR valida.',
                'notas' => [],
            ];
        }

        $cdrResponse = $result->getCdrResponse();

        return [
            'cdr_zip' => $result->getCdrZip(),
            'codigo' => (int) ($cdrResponse?->getCode() ?? 0),
            'mensaje' => $cdrResponse?->getDescription() ?? 'Sin descripcion SUNAT',
            'notas' => $cdrResponse?->getNotes() ?? [],
        ];
    }
}
