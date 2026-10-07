<?php

namespace App\Actions\Billing;

use App\Contracts\SunatClientInterface;
use App\Models\CompanySetting;
use App\Models\ElectronicDocument;
use Illuminate\Validation\ValidationException;

/**
 * Saca la verdad de SUNAT sobre un comprobante que ya se envió y del que no
 * llegó (o no se guardó) la respuesta. Es el paso obligatorio antes de
 * reemplazar un comprobante ya enviado: si SUNAT lo aceptó, aunque nosotros
 * tengamos un error de red, el comprobante sigue vigente y no puede
 * reemplazarse con otro número.
 */
class ConsultarCdrSunat
{
    public function __construct(protected EmitElectronicDocument $emitElectronicDocument) {}

    /**
     * Consulta a SUNAT y, si el comprobante está allí, deja registrado su CDR
     * y su estado real.
     *
     * @return array{estado:'registrado'|'no_registrado'|'sin_respuesta'}
     */
    public function handle(ElectronicDocument $document): array
    {
        $respuesta = app(SunatClientInterface::class)->consultCdr(
            (string) CompanySetting::current()->ruc,
            $document->tipoDocSunat(),
            $document->serie,
            (int) $document->correlativo,
        );

        if ($respuesta['estado'] === 'registrado') {
            $this->emitElectronicDocument->registrarRespuesta($document->refresh(), $respuesta);
        }

        return ['estado' => $respuesta['estado']];
    }

    /**
     * Solo deja reemplazar el comprobante cuando SUNAT confirma que no lo
     * tiene. Sin respuesta se corta: es preferible reintentar el mismo XML a
     * emitir un segundo comprobante por la misma venta.
     */
    public function exigirReemplazable(ElectronicDocument $document): void
    {
        $estado = $this->handle($document)['estado'];

        if ($estado === 'registrado') {
            throw ValidationException::withMessages([
                'estado' => 'SUNAT ya tiene este comprobante (quedó aceptado aunque faltara su CDR). Ya no se reemplaza: para corregirlo emite una nota de crédito.',
            ]);
        }

        if ($estado === 'sin_respuesta') {
            throw ValidationException::withMessages([
                'estado' => 'SUNAT no respondió al consultar el comprobante, así que no se puede saber si lo tiene. No se reemplaza; vuelve a intentarlo cuando responda.',
            ]);
        }
    }
}
