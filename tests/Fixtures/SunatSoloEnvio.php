<?php

namespace Tests\Fixtures;

use App\Contracts\SunatClientInterface;
use LogicException;

/**
 * Base de los SUNAT simulados que solo prueban el envío de comprobantes:
 * si una prueba llega a una baja o un resumen sin simularlo, falla claro.
 */
abstract class SunatSoloEnvio implements SunatClientInterface
{
    public function sendSummary(string $xmlSigned, string $documentName): array
    {
        throw new LogicException('Esta prueba no simula comunicaciones de baja ni resúmenes.');
    }

    public function getStatus(string $ticket): array
    {
        throw new LogicException('Esta prueba no simula la consulta de tickets.');
    }
}
