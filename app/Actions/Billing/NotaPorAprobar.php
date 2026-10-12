<?php

namespace App\Actions\Billing;

use App\Models\ElectronicDocument;
use App\Models\NoteRequest;
use App\Services\Billing\DesgloseNota;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Aprobación del Gerente para las notas de crédito y débito (V2/S17): el
 * vendedor las pide, quedan "por aprobar" sin número ni envío, y el Gerente
 * las aprueba (se emiten con el flujo normal) o las rechaza con un motivo.
 */
class NotaPorAprobar
{
    public function __construct(
        protected IssueCreditNote $issueCreditNote,
        protected IssueDebitNote $issueDebitNote,
    ) {}

    /**
     * @param  array<string, mixed>  $opciones
     *
     * @throws ValidationException
     */
    public function solicitar(ElectronicDocument $original, string $tipo, string $motivoCatalogo, string $detalle, float $importe, int $solicitanteId, array $opciones = []): NoteRequest
    {
        $tipo === 'nota_credito'
            ? $this->issueCreditNote->validar($original, $motivoCatalogo)
            : $this->issueDebitNote->validar($original);

        app(DesgloseNota::class)->calcular($original, $tipo, $motivoCatalogo, $importe, $opciones);

        if (NoteRequest::query()->where('electronic_document_id', $original->id)->where('tipo', $tipo)->where('estado', 'por_aprobar')->exists()) {
            throw ValidationException::withMessages(['solicitud' => 'Ya hay una solicitud igual esperando al Gerente. Espera su respuesta antes de pedir otra.']);
        }

        if ($tipo === 'nota_credito') {
            $acreditado = (float) ElectronicDocument::query()
                ->where('cpe_afectado_id', $original->id)
                ->where('tipo', 'nota_credito')
                ->whereNotIn('sunat_estado', ['rechazado'])
                ->sum('importe');
            $total = (float) $original->sale()->value('total');

            if ($importe + $acreditado > $total + 0.001) {
                throw ValidationException::withMessages(['importe' => 'El importe no puede superar lo que queda por acreditar del comprobante (S/ '.number_format(max($total - $acreditado, 0), 2).').']);
            }
        }

        return NoteRequest::create([
            'electronic_document_id' => $original->id,
            'tipo' => $tipo,
            'motivo_catalogo' => $motivoCatalogo,
            'detalle' => $detalle,
            'importe' => $importe,
            'nota_datos' => $opciones,
            'solicitado_por' => $solicitanteId,
        ]);
    }

    /**
     * Crea la nota (toma su número). El envío a SUNAT lo hace quien llama,
     * fuera de esta transacción, como cuando el Gerente la emite directo.
     *
     * @throws ValidationException
     */
    public function aprobar(NoteRequest $solicitud, int $gerenteId): ElectronicDocument
    {
        return DB::transaction(function () use ($solicitud, $gerenteId) {
            $solicitud = $this->bloquearPendiente($solicitud);
            $original = $solicitud->original()->firstOrFail();

            $nota = $solicitud->tipo === 'nota_credito'
                ? $this->issueCreditNote->handle($original, $solicitud->motivo_catalogo, $solicitud->detalle, (float) $solicitud->importe, $solicitud->nota_datos ?? [])
                : $this->issueDebitNote->handle($original, $solicitud->motivo_catalogo, $solicitud->detalle, (float) $solicitud->importe);

            $solicitud->update([
                'estado' => 'aprobada',
                'revisado_por' => $gerenteId,
                'revisado_at' => now(),
                'nota_id' => $nota->id,
            ]);

            return $nota;
        });
    }

    /**
     * @throws ValidationException
     */
    public function rechazar(NoteRequest $solicitud, int $gerenteId, string $motivo): void
    {
        DB::transaction(function () use ($solicitud, $gerenteId, $motivo): void {
            $this->bloquearPendiente($solicitud)->update([
                'estado' => 'rechazada',
                'revisado_por' => $gerenteId,
                'revisado_at' => now(),
                'motivo_rechazo' => $motivo,
            ]);
        });
    }

    protected function bloquearPendiente(NoteRequest $solicitud): NoteRequest
    {
        $solicitud = NoteRequest::query()->lockForUpdate()->findOrFail($solicitud->id);

        if ($solicitud->estado !== 'por_aprobar') {
            throw ValidationException::withMessages([
                'solicitud' => 'Esta solicitud ya fue revisada.',
            ]);
        }

        return $solicitud;
    }
}
