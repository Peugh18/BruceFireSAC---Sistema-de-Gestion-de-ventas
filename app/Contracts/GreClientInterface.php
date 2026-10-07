<?php

namespace App\Contracts;

interface GreClientInterface
{
    /**
     * Envía el ZIP del XML firmado de una guía (con su hash SHA-256) a la API
     * REST de SUNAT y devuelve el ticket.
     */
    public function enviar(string $nombreArchivo, string $zip): string;

    /**
     * Consulta el ticket. "en_proceso" mientras SUNAT no termina (código 98);
     * "aceptada" solo con código 0 y CDR legible.
     *
     * @return array{en_proceso:bool,aceptada:bool,cdr_zip:string|null,codigo:string,mensaje:string}
     */
    public function consultar(string $ticket): array;
}
