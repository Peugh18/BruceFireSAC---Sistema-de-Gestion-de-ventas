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

    /**
     * Envia una comunicacion de baja (RA) o un resumen diario (RC) firmado.
     * SUNAT responde con un ticket que se consulta despues con getStatus().
     *
     * @return array{ticket:string|null,mensaje:string}
     */
    public function sendSummary(string $xmlSigned, string $documentName): array;

    /**
     * Consulta el ticket de una comunicacion de baja o resumen diario.
     * en_proceso = true mientras SUNAT no termina (codigo 98).
     *
     * @return array{en_proceso:bool,cdr_zip:string|null,codigo:int,mensaje:string,notas:list<string>}
     */
    public function getStatus(string $ticket): array;
}
