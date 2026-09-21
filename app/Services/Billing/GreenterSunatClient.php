<?php

namespace App\Services\Billing;

use App\Contracts\SunatClientInterface;
use Greenter\Model\Response\BillResult;
use Greenter\Model\Sale\Invoice;
use Greenter\See;
use RuntimeException;

class GreenterSunatClient implements SunatClientInterface
{
    /**
     * @return array{cdr_zip:string|null,codigo:int,mensaje:string,notas:list<string>}
     */
    public function send(string $xmlSigned, string $documentName): array
    {
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

        // El primer parámetro debe ser el FQCN del documento (lo que
        // XmlBuilderResolver/WsSenderResolver esperan para resolver builder y
        // sender), no un alias corto como 'invoice': con un alias,
        // XmlBuilderResolver::findBuilderType() revienta con
        // substr(strrchr('invoice', '\\'), 1) porque 'invoice' no tiene '\\'.
        // Solo se soportan Factura/Boleta por ahora (GreenterService::buildInvoice).
        $result = $see->sendXml(Invoice::class, $documentName, $xmlSigned);

        if (! $result || ! $result->isSuccess()) {
            $error = $result?->getError();

            return [
                'cdr_zip' => null,
                'codigo' => 500,
                'mensaje' => $error?->getMessage() ?? 'Error de comunicación con SUNAT.',
                'notas' => [],
            ];
        }

        if (! $result instanceof BillResult) {
            return [
                'cdr_zip' => null,
                'codigo' => 500,
                'mensaje' => 'SUNAT no devolvió una respuesta CDR válida.',
                'notas' => [],
            ];
        }

        $cdrResponse = $result->getCdrResponse();

        return [
            'cdr_zip' => $result->getCdrZip(),
            'codigo' => (int) ($cdrResponse?->getCode() ?? 0),
            'mensaje' => $cdrResponse?->getDescription() ?? 'Sin descripción SUNAT',
            'notas' => $cdrResponse?->getNotes() ?? [],
        ];
    }
}
