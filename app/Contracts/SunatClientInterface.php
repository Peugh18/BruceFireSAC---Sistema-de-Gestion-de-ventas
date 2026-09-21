<?php

namespace App\Contracts;

interface SunatClientInterface
{
    /**
     * Envia un XML ya firmado (real, generado por GreenterService::sign())
     * a SUNAT. La implementacion real usa Greenter, pero los tests enlazan
     * una implementacion fake porque las credenciales reales de Clave SOL
     * viven en .env y aun no estan disponibles en producción.
     *
     * @return array{cdr_zip:string|null,codigo:int,mensaje:string,notas?:list<string>}
     */
    public function send(string $xmlSigned, string $documentName): array;
}
