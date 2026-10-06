<?php

namespace App\Actions\Billing;

use App\Actions\Sales\RevertSale;
use App\Contracts\SunatClientInterface;
use App\Models\CashRegister;
use App\Models\ElectronicDocument;
use App\Models\Sale;
use App\Services\Billing\GreenterService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Comunicación de baja (S7, SUNAT.md §1): anula ante SUNAT un comprobante
 * aceptado que no se entregó al cliente, dentro de los 7 días calendario
 * siguientes a la recepción del CDR. Facturas y sus notas van por
 * Comunicación de Baja (RA); boletas y sus notas, por Resumen Diario (RC)
 * con estado 3. SUNAT responde con un ticket: el comprobante queda
 * "baja_pendiente" y pasa a "anulado" solo cuando SUNAT acepta.
 */
class VoidElectronicDocument
{
    public function __construct(
        protected GreenterService $greenterService,
        protected ReserveNextCorrelativo $reserveNextCorrelativo,
        protected RevertSale $revertSale,
    ) {}

    /**
     * @throws ValidationException
     */
    public function handle(ElectronicDocument $document, string $motivo, bool $noEntregado): ElectronicDocument
    {
        $this->validar($document, $noEntregado);

        // Correlativo diario: una "serie" RA o RC por cada día.
        $tipoSerie = 'baja_'.now()->format('Ymd');
        $serieDiaria = GreenterService::seInformaPorResumen($document) ? 'RC' : 'RA';
        $correlativo = $this->reserveNextCorrelativo->handle($tipoSerie, $serieDiaria);

        $baja = $this->greenterService->buildBaja($document, $motivo, $correlativo);
        $xml = $this->greenterService->sign($baja);
        $nombre = $baja->getName();

        try {
            $respuesta = app(SunatClientInterface::class)->sendSummary($xml, $nombre);
        } catch (Throwable $e) {
            report($e);
            $respuesta = ['ticket' => null, 'mensaje' => 'SUNAT no responde.'];
        }

        if ($respuesta['ticket'] === null) {
            // SUNAT no recibió nada: el número del día queda libre.
            $this->reserveNextCorrelativo->liberar($tipoSerie, $serieDiaria, $correlativo);

            throw ValidationException::withMessages([
                'electronic_document' => "SUNAT no recibió la comunicación de baja: {$respuesta['mensaje']} Inténtalo de nuevo en unos minutos.",
            ]);
        }

        Storage::disk('local')->put("xml/{$nombre}.xml", $xml);

        $document->update([
            'baja_estado_previo' => $document->sunat_estado,
            'sunat_estado' => 'baja_pendiente',
            'baja_nombre' => $nombre,
            'baja_ticket' => $respuesta['ticket'],
            'baja_motivo' => mb_substr($motivo, 0, 100),
            'baja_mensaje' => $respuesta['mensaje'],
        ]);

        // SUNAT suele resolver el ticket en segundos; si no, lo consulta la
        // tarea programada (billing:enviar-programados).
        try {
            return $this->consultar($document);
        } catch (Throwable $e) {
            report($e);

            return $document->refresh();
        }
    }

    /**
     * Consulta el ticket. Aceptada: el comprobante queda anulado y, si es
     * una factura o boleta, la venta se revierte igual que con una nota de
     * crédito de anulación total. Rechazada: vuelve a su estado anterior.
     */
    public function consultar(ElectronicDocument $document): ElectronicDocument
    {
        if ($document->sunat_estado !== 'baja_pendiente' || ! $document->baja_ticket) {
            return $document;
        }

        $estado = app(SunatClientInterface::class)->getStatus($document->baja_ticket);

        if ($estado['en_proceso']) {
            return $document->refresh();
        }

        $mensaje = $estado['mensaje'].($estado['notas'] === [] ? '' : ' | '.implode(' | ', $estado['notas']));

        if ($estado['codigo'] !== 0 || $estado['cdr_zip'] === null) {
            $document->update([
                'sunat_estado' => $document->baja_estado_previo ?? 'aceptado',
                'baja_mensaje' => "SUNAT rechazó la baja: {$mensaje}",
            ]);

            return $document->refresh();
        }

        Storage::disk('local')->put("cdr/R-{$document->baja_nombre}.zip", $estado['cdr_zip']);

        DB::transaction(function () use ($document, $mensaje): void {
            $document->update(['sunat_estado' => 'anulado', 'baja_mensaje' => $mensaje]);

            if (! in_array($document->tipo, ['factura', 'boleta'], true)) {
                return;
            }

            $sale = Sale::query()->lockForUpdate()->findOrFail($document->sale_id);

            if ($sale->estado !== 'anulada') {
                $this->revertSale->handle($sale, 'por comunicación de baja');
            }
        });

        return $document->refresh();
    }

    /**
     * @throws ValidationException
     */
    protected function validar(ElectronicDocument $document, bool $noEntregado): void
    {
        $error = fn (string $mensaje) => ValidationException::withMessages(['electronic_document' => $mensaje]);

        if (! in_array($document->sunat_estado, ['aceptado', 'observado'], true)) {
            throw $error('Solo se da de baja un comprobante que SUNAT ya aceptó (con su CDR).');
        }

        if (! $noEntregado) {
            throw $error('La baja solo procede si el comprobante no se entregó al cliente. Si ya lo entregaste, emite una nota de crédito.');
        }

        if (today()->gt($document->vencimientoBaja())) {
            throw $error('El plazo para la comunicación de baja venció el '.$document->vencimientoBaja()->format('d/m/Y').' (7 días desde que SUNAT lo aceptó). Para anularlo emite una nota de crédito.');
        }

        $sale = $document->sale()->firstOrFail();

        if ($sale->estado === 'anulada') {
            throw $error('La venta ya está anulada: este comprobante no admite comunicación de baja.');
        }

        $tieneNotas = ElectronicDocument::query()
            ->where('cpe_afectado_id', $document->id)
            ->whereNotIn('sunat_estado', ['rechazado', 'anulado'])
            ->exists();

        if ($tieneNotas) {
            throw $error('Este comprobante tiene notas de crédito o débito: da de baja primero esas notas.');
        }

        // Si la venta se cobró en efectivo, la devolución sale de la caja abierta.
        if (in_array($document->tipo, ['factura', 'boleta'], true)) {
            CashRegister::exigirAbiertaParaDevolverEfectivo($sale, 'electronic_document');
        }
    }
}
