<?php

namespace App\Console\Commands;

use App\Actions\Billing\ConsultarCdrSunat;
use App\Actions\Billing\EmitElectronicDocument;
use App\Actions\Billing\VoidElectronicDocument;
use App\Models\ElectronicDocument;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
use Throwable;

#[Signature('billing:enviar-programados')]
#[Description('Envía a SUNAT las facturas y boletas cuya ventana de revisión ya venció y consulta las bajas en proceso')]
class EnviarComprobantesProgramados extends Command
{
    /**
     * Si SUNAT no responde, el comprobante sigue sin CDR y se reintenta en la
     * siguiente pasada con el mismo XML firmado (el plazo legal es de 3 días
     * calendario para factura y 5 para boleta).
     */
    public function handle(EmitElectronicDocument $emitElectronicDocument, VoidElectronicDocument $voidElectronicDocument, ConsultarCdrSunat $consultarCdrSunat): void
    {
        // No solo «por enviar»: un «pendiente» (emisión inmediata) o un
        // «excepcion» (error de red) también se reintentan, o se vence el
        // plazo legal sin que nadie se dé cuenta.
        $documentos = ElectronicDocument::query()
            ->where(function (Builder $query): void {
                $query->where('sunat_estado', 'por_enviar')
                    ->where('enviar_desde', '<=', now());
            })
            ->orWhere(function (Builder $query): void {
                $query->whereIn('sunat_estado', ['pendiente', 'excepcion']);
            })
            ->orderBy('enviar_desde')
            ->orderBy('id')
            ->get();

        $enviados = 0;

        foreach ($documentos as $documento) {
            try {
                // Si ya hubo un intento, la respuesta pudo perderse: primero se
                // pregunta a SUNAT. Si ya lo tiene se deja con su CDR y no se
                // reenvía; si no lo tiene, o no responde, se reintenta con el
                // mismo XML firmado (reenviarlo no es reemplazarlo).
                if ($documento->resultadoDesconocidoDeSunat()
                    && $consultarCdrSunat->handle($documento)['estado'] === 'registrado') {
                    continue;
                }

                $emitElectronicDocument->sendDocument($documento->fresh());
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
