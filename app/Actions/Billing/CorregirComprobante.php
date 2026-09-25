<?php

namespace App\Actions\Billing;

use App\Actions\Sales\ValidarComprobanteCliente;
use App\Models\Client;
use App\Models\ElectronicDocument;
use App\Models\Sale;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CorregirComprobante
{
    public function __construct(
        protected EmitElectronicDocument $emitElectronicDocument,
        protected ValidarComprobanteCliente $validarComprobanteCliente,
    ) {}

    /**
     * Corrige el tipo de comprobante y/o el cliente sin nota de crédito:
     *  - Por enviar: SUNAT aún no lo conoce. Mismo tipo → se regenera con el
     *    mismo número; otro tipo → se libera el número y se toma uno de la
     *    otra serie. Se respeta la hora de envío original.
     *  - Rechazado/excepción: no tiene validez; se emite uno nuevo con otro
     *    número y fecha de hoy (SUNAT no permite reutilizar el número).
     * Un comprobante aceptado solo se corrige con nota de crédito.
     */
    public function handle(Sale $sale, string $comprobanteTipo, int $clientId, ?int $userId = null): ElectronicDocument
    {
        $documento = $this->comprobanteActual($sale);

        if (! in_array($comprobanteTipo, ['factura', 'boleta'], true)) {
            throw ValidationException::withMessages(['comprobante_tipo' => 'Elige factura o boleta.']);
        }

        $client = Client::query()->findOrFail($clientId);
        $this->validarComprobanteCliente->handle($client, $comprobanteTipo, (float) $sale->total, exigirRucHabido: true);

        return DB::transaction(function () use ($sale, $documento, $comprobanteTipo, $client, $userId) {
            $antes = [
                'comprobante' => "{$documento->serie}-{$documento->correlativo}",
                'comprobante_tipo' => $sale->comprobante_tipo,
                'client_id' => $sale->client_id,
            ];

            $sale->update(['comprobante_tipo' => $comprobanteTipo, 'client_id' => $client->id]);
            $sale->items()->whereNotNull('equipment_id')->with('equipment')->get()
                ->each(fn ($item) => $item->equipment?->update(['client_id' => $client->id]));
            $sale->unsetRelation('client');

            $nuevo = match (true) {
                $documento->estaPorEnviar() && $documento->tipo === $comprobanteTipo => $this->regenerar($documento),
                $documento->estaPorEnviar() => $this->cambiarDeSerie($sale, $documento),
                default => $this->emitElectronicDocument->programar($sale, now()),
            };

            AuditLogger::log(
                action: 'comprobante.corregido',
                entity: $sale,
                oldValues: $antes,
                newValues: [
                    'comprobante' => "{$nuevo->serie}-{$nuevo->correlativo}",
                    'comprobante_tipo' => $comprobanteTipo,
                    'client_id' => $client->id,
                ],
                userId: $userId,
            );

            return $nuevo;
        });
    }

    protected function comprobanteActual(Sale $sale): ElectronicDocument
    {
        $documento = $sale->electronicDocuments()
            ->whereIn('tipo', ['factura', 'boleta'])
            ->latest('id')
            ->first();

        if (! $documento || ! ($documento->estaPorEnviar() || $documento->fueRechazado())) {
            throw ValidationException::withMessages([
                'comprobante' => 'Este comprobante ya fue aceptado por SUNAT: para corregirlo emite una nota de crédito.',
            ]);
        }

        return $documento;
    }

    protected function regenerar(ElectronicDocument $documento): ElectronicDocument
    {
        $this->emitElectronicDocument->prepararDocumento($documento);

        return $documento->refresh();
    }

    protected function cambiarDeSerie(Sale $sale, ElectronicDocument $documento): ElectronicDocument
    {
        $enviarDesde = $documento->enviar_desde;
        $fechaEmision = $documento->fecha_emision;

        $this->emitElectronicDocument->descartarPorEnviar($documento);

        $nuevo = $this->emitElectronicDocument->programar($sale, $fechaEmision);

        if ($nuevo->estaPorEnviar() && $enviarDesde) {
            $nuevo->update(['enviar_desde' => $enviarDesde]);
        }

        return $nuevo->refresh();
    }
}
