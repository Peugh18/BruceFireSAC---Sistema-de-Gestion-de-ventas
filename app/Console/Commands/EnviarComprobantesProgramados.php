<?php

namespace App\Console\Commands;

use App\Actions\Billing\EmitElectronicDocument;
use App\Actions\Billing\VoidElectronicDocument;
use App\Models\ElectronicDocument;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

#[Signature('billing:enviar-programados')]
#[Description('Envía a SUNAT las facturas y boletas cuya ventana de revisión ya venció y consulta las bajas en proceso')]
class EnviarComprobantesProgramados extends Command
{
    /**
     * Si SUNAT no responde, el comprobante sigue "por enviar" y se reintenta
     * en la siguiente pasada (el plazo legal es de 3 días calendario).
     */
    public function handle(EmitElectronicDocument $emitElectronicDocument, VoidElectronicDocument $voidElectronicDocument): void
    {
        $documentos = ElectronicDocument::query()
            ->where('sunat_estado', 'por_enviar')
            ->where('enviar_desde', '<=', now())
            ->orderBy('enviar_desde')
            ->get();

        $enviados = 0;

        foreach ($documentos as $documento) {
            try {
                $emitElectronicDocument->sendDocument($documento);
                $enviados++;
            } catch (Throwable $exception) {
                Log::warning("No se pudo enviar {$documento->serie}-{$documento->correlativo} a SUNAT; se reintentará.", [
                    'electronic_document_id' => $documento->id,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        $this->info("{$enviados} de {$documentos->count()} comprobante(s) enviados a SUNAT.");

        // Comunicaciones de baja cuyo ticket SUNAT aún no resolvía.
        ElectronicDocument::query()
            ->where('sunat_estado', 'baja_pendiente')
            ->each(function (ElectronicDocument $documento) use ($voidElectronicDocument): void {
                try {
                    $voidElectronicDocument->consultar($documento);
                } catch (Throwable $exception) {
                    Log::warning("No se pudo consultar la baja de {$documento->serie}-{$documento->correlativo}; se reintentará.", [
                        'electronic_document_id' => $documento->id,
                        'error' => $exception->getMessage(),
                    ]);
                }
            });
    }
}
